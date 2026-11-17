import { router } from '@inertiajs/react';
import { useState } from 'react';
import { adminUrl } from '@imajiner/admin-sdk';
import { Button, Card, CardContent, Input, PageHeader, useBreadcrumbs } from '@imajiner/admin-sdk/ui';

type Booking = { id: number; kind: 'order' | 'block'; start: string; end: string; free: string; stage: string | null; label: string; href: string | null };
type Unit = { id: number; code: string; available: boolean; condition: string; product: string; variant: string; sku: string; bookings: Booking[] };
type Props = {
    branches: { id: number; name: string }[];
    filters: { branch: number; from: string; days: number; search: string; page: number };
    dates: string[];
    today: string;
    total: number;
    perPage: number;
    units: Unit[];
    stages: Record<string, string>;
};

const COLORS: Record<string, string> = {
    to_pick_up: '#3b82f6', out: '#16a34a', late: '#dc2626', returned: '#64748b', block: '#a16207',
};

function addDays(date: string, days: number): string {
    const d = new Date(`${date}T00:00:00`);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

function dayLabel(date: string): { day: string; weekday: string } {
    const d = new Date(`${date}T00:00:00`);
    return { day: String(d.getDate()), weekday: d.toLocaleDateString(undefined, { weekday: 'short' }) };
}

/** What a day is for a unit: a booking, its rest days, or free. */
function cellOf(unit: Unit, date: string): { booking: Booking; rest: boolean } | null {
    for (const b of unit.bookings) {
        if (date >= b.start && date <= b.end) return { booking: b, rest: false };
        if (date > b.end && date < b.free) return { booking: b, rest: true };
    }
    return null;
}

export default function Calendar({ branches, filters, dates, today, total, perPage, units, stages }: Props) {
    useBreadcrumbs([{ label: 'Rentals' }, { label: 'Calendar' }]);
    const [search, setSearch] = useState(filters.search);
    const visit = (changes: Partial<Props['filters']>) =>
        router.get(adminUrl('rental/calendar'), { ...filters, ...changes }, { preserveState: true, preserveScroll: true, replace: true });
    const pages = Math.max(1, Math.ceil(total / perPage));

    return (
        <div>
            <PageHeader title="Calendar" description="Each unit's rentals, blocked dates and rest days. Click a rental to open its order." />
            <Card>
                <CardContent>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center', marginBottom: 12 }}>
                        <select aria-label="Branch" value={filters.branch} onChange={(e) => visit({ branch: Number(e.target.value), page: 1 })}
                            style={{ height: 36, borderRadius: 8, border: '1px solid var(--border)', padding: '0 8px', background: 'var(--background)' }}>
                            {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                        </select>
                        <form onSubmit={(e) => { e.preventDefault(); visit({ search, page: 1 }); }} style={{ display: 'flex', gap: 8 }}>
                            <Input value={search} onChange={(e: { target: { value: string } }) => setSearch(e.target.value)} placeholder="Unit code, SKU or size" style={{ width: 220 }} />
                        </form>
                        <div style={{ display: 'flex', gap: 4, marginLeft: 'auto' }}>
                            <Button variant="outline" size="sm" onClick={() => visit({ from: addDays(filters.from, -filters.days) })}>←</Button>
                            <Button variant="outline" size="sm" onClick={() => visit({ from: today })}>Today</Button>
                            <Button variant="outline" size="sm" onClick={() => visit({ from: addDays(filters.from, filters.days) })}>→</Button>
                            {[7, 14, 28].map((d) => (
                                <Button key={d} variant={d === filters.days ? 'default' : 'outline'} size="sm" onClick={() => visit({ days: d })}>{d} days</Button>
                            ))}
                        </div>
                    </div>

                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, fontSize: 12, marginBottom: 8, color: 'var(--muted-foreground)' }}>
                        {Object.entries(stages).map(([key, label]) => <Legend key={key} color={COLORS[key]} label={label} />)}
                        <Legend color={COLORS.block} label="Blocked" />
                        <Legend color="var(--muted)" label="Rest days" striped />
                    </div>

                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ borderCollapse: 'separate', borderSpacing: 0, fontSize: 12, minWidth: '100%' }}>
                            <thead>
                                <tr>
                                    <th style={{ ...sticky, textAlign: 'left', padding: '4px 8px', background: 'var(--card)' }}>Unit</th>
                                    {dates.map((date) => {
                                        const { day, weekday } = dayLabel(date);
                                        return (
                                            <th key={date} style={{ padding: '4px 0', minWidth: 30, fontWeight: date === today ? 700 : 500, color: date === today ? 'var(--primary)' : undefined }}>
                                                <div>{weekday}</div><div>{day}</div>
                                            </th>
                                        );
                                    })}
                                </tr>
                            </thead>
                            <tbody>
                                {units.length === 0 && (
                                    <tr><td colSpan={dates.length + 1} style={{ padding: 24, textAlign: 'center', color: 'var(--muted-foreground)' }}>No units here.</td></tr>
                                )}
                                {units.map((unit) => (
                                    <tr key={unit.id}>
                                        <td style={{ ...sticky, padding: '4px 8px', background: 'var(--card)', borderTop: '1px solid var(--border)', whiteSpace: 'nowrap' }}>
                                            <a href={adminUrl(`rental/units/${unit.id}/edit`)} style={{ fontWeight: 600 }}>{unit.code}</a>
                                            <div style={{ color: 'var(--muted-foreground)' }}>{[unit.product, unit.variant].filter(Boolean).join(' · ')}</div>
                                            {!unit.available && <div style={{ color: COLORS.late }}>{unit.condition === 'good' ? 'Inactive' : unit.condition}</div>}
                                        </td>
                                        {dates.map((date) => <Cell key={date} unit={unit} date={date} />)}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {pages > 1 && (
                        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', alignItems: 'center', marginTop: 12 }}>
                            <Button variant="outline" size="sm" disabled={filters.page <= 1} onClick={() => visit({ page: filters.page - 1 })}>Previous</Button>
                            <span>{filters.page} / {pages}</span>
                            <Button variant="outline" size="sm" disabled={filters.page >= pages} onClick={() => visit({ page: filters.page + 1 })}>Next</Button>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

const sticky = { position: 'sticky' as const, left: 0, zIndex: 1 };

function Legend({ color, label, striped = false }: { color: string; label: string; striped?: boolean }) {
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
            <span style={{ width: 12, height: 12, borderRadius: 3, background: striped ? stripes : color }} />{label}
        </span>
    );
}

const stripes = 'repeating-linear-gradient(45deg, var(--muted), var(--muted) 3px, var(--border) 3px, var(--border) 6px)';

function Cell({ unit, date }: { unit: Unit; date: string }) {
    const cell = cellOf(unit, date);
    const base = { borderTop: '1px solid var(--border)', borderLeft: '1px solid var(--border)', height: 34, padding: 0 };
    if (cell === null) return <td style={{ ...base, background: unit.available ? undefined : 'var(--muted)' }} />;
    const { booking, rest } = cell;
    const color = booking.kind === 'block' ? COLORS.block : COLORS[booking.stage ?? 'to_pick_up'];
    const title = `${booking.label}\n${booking.start} – ${booking.end}${rest ? ' (rest days)' : ''}`;
    const isStart = date === booking.start;
    const body = (
        <div title={title} style={{ height: '100%', background: rest ? stripes : color, color: '#fff', overflow: 'hidden', whiteSpace: 'nowrap', fontSize: 11, padding: '0 3px', lineHeight: '34px' }}>
            {isStart && !rest ? booking.label : ''}
        </div>
    );

    return <td style={base}>{booking.href && !rest ? <a href={booking.href}>{body}</a> : body}</td>;
}
