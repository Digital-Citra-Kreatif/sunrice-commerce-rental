function y() {
  const t = globalThis.Sunrice;
  if (!t)
    throw new Error("[sunrice] This script runs outside the Sunrice admin panel (window.Sunrice is missing).");
  return t;
}
function B(t) {
  const r = y();
  if (r.version !== 3)
    throw new Error(`[sunrice] This plugin was built for admin runtime 3, but the panel runs ${r.version}. Rebuild it with a matching @imajiner/admin-sdk.`);
  for (const [i, o] of Object.entries(t.pages ?? {}))
    r.registerPage(i, o);
  for (const [i, o] of Object.entries(t.lazyPages ?? {}))
    r.registerPageLoader(i, o);
}
function f(t = "") {
  return y().adminUrl(t);
}
const S = globalThis.Sunrice;
if (!S) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const e = S.modules["react/jsx-runtime"], w = globalThis.Sunrice;
if (!w) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const $ = w.modules["@inertiajs/react"], k = globalThis.Sunrice;
if (!k) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const D = k.modules.react, C = globalThis.Sunrice;
if (!C) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const a = C.modules["@imajiner/admin-sdk/ui"], u = {
  to_pick_up: "#3b82f6",
  out: "#16a34a",
  late: "#dc2626",
  returned: "#64748b",
  block: "#a16207"
};
function j(t, r) {
  const i = /* @__PURE__ */ new Date(`${t}T00:00:00`);
  return i.setDate(i.getDate() + r), i.toISOString().slice(0, 10);
}
function R(t) {
  const r = /* @__PURE__ */ new Date(`${t}T00:00:00`);
  return { day: String(r.getDate()), weekday: r.toLocaleDateString(void 0, { weekday: "short" }) };
}
function E(t, r) {
  for (const i of t.bookings) {
    if (r >= i.start && r <= i.end) return { booking: i, rest: !1 };
    if (r > i.end && r < i.free) return { booking: i, rest: !0 };
  }
  return null;
}
function I({ branches: t, filters: r, dates: i, today: o, total: s, perPage: d, units: h, stages: x }) {
  a.useBreadcrumbs([{ label: "Rentals" }, { label: "Calendar" }]);
  const [p, g] = D.useState(r.search), l = (n) => $.router.get(f("rental/calendar"), { ...r, ...n }, { preserveState: !0, preserveScroll: !0, replace: !0 }), m = Math.max(1, Math.ceil(s / d));
  return /* @__PURE__ */ e.jsxs("div", { children: [
    /* @__PURE__ */ e.jsx(a.PageHeader, { title: "Calendar", description: "Each unit's rentals, blocked dates and rest days. Click a rental to open its order." }),
    /* @__PURE__ */ e.jsx(a.Card, { children: /* @__PURE__ */ e.jsxs(a.CardContent, { children: [
      /* @__PURE__ */ e.jsxs("div", { style: { display: "flex", flexWrap: "wrap", gap: 8, alignItems: "center", marginBottom: 12 }, children: [
        /* @__PURE__ */ e.jsx(
          "select",
          {
            "aria-label": "Branch",
            value: r.branch,
            onChange: (n) => l({ branch: Number(n.target.value), page: 1 }),
            style: { height: 36, borderRadius: 8, border: "1px solid var(--border)", padding: "0 8px", background: "var(--background)" },
            children: t.map((n) => /* @__PURE__ */ e.jsx("option", { value: n.id, children: n.name }, n.id))
          }
        ),
        /* @__PURE__ */ e.jsx("form", { onSubmit: (n) => {
          n.preventDefault(), l({ search: p, page: 1 });
        }, style: { display: "flex", gap: 8 }, children: /* @__PURE__ */ e.jsx(a.Input, { value: p, onChange: (n) => g(n.target.value), placeholder: "Unit code, SKU or size", style: { width: 220 } }) }),
        /* @__PURE__ */ e.jsxs("div", { style: { display: "flex", gap: 4, marginLeft: "auto" }, children: [
          /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", size: "sm", onClick: () => l({ from: j(r.from, -r.days) }), children: "←" }),
          /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", size: "sm", onClick: () => l({ from: o }), children: "Today" }),
          /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", size: "sm", onClick: () => l({ from: j(r.from, r.days) }), children: "→" }),
          [7, 14, 28].map((n) => /* @__PURE__ */ e.jsxs(a.Button, { variant: n === r.days ? "default" : "outline", size: "sm", onClick: () => l({ days: n }), children: [
            n,
            " days"
          ] }, n))
        ] })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { style: { display: "flex", flexWrap: "wrap", gap: 12, fontSize: 12, marginBottom: 8, color: "var(--muted-foreground)" }, children: [
        Object.entries(x).map(([n, c]) => /* @__PURE__ */ e.jsx(b, { color: u[n], label: c }, n)),
        /* @__PURE__ */ e.jsx(b, { color: u.block, label: "Blocked" }),
        /* @__PURE__ */ e.jsx(b, { color: "var(--muted)", label: "Rest days", striped: !0 })
      ] }),
      /* @__PURE__ */ e.jsx("div", { style: { overflowX: "auto" }, children: /* @__PURE__ */ e.jsxs("table", { style: { borderCollapse: "separate", borderSpacing: 0, fontSize: 12, minWidth: "100%" }, children: [
        /* @__PURE__ */ e.jsx("thead", { children: /* @__PURE__ */ e.jsxs("tr", { children: [
          /* @__PURE__ */ e.jsx("th", { style: { ...v, textAlign: "left", padding: "4px 8px", background: "var(--card)" }, children: "Unit" }),
          i.map((n) => {
            const { day: c, weekday: z } = R(n);
            return /* @__PURE__ */ e.jsxs("th", { style: { padding: "4px 0", minWidth: 30, fontWeight: n === o ? 700 : 500, color: n === o ? "var(--primary)" : void 0 }, children: [
              /* @__PURE__ */ e.jsx("div", { children: z }),
              /* @__PURE__ */ e.jsx("div", { children: c })
            ] }, n);
          })
        ] }) }),
        /* @__PURE__ */ e.jsxs("tbody", { children: [
          h.length === 0 && /* @__PURE__ */ e.jsx("tr", { children: /* @__PURE__ */ e.jsx("td", { colSpan: i.length + 1, style: { padding: 24, textAlign: "center", color: "var(--muted-foreground)" }, children: "No units here." }) }),
          h.map((n) => /* @__PURE__ */ e.jsxs("tr", { children: [
            /* @__PURE__ */ e.jsxs("td", { style: { ...v, padding: "4px 8px", background: "var(--card)", borderTop: "1px solid var(--border)", whiteSpace: "nowrap" }, children: [
              /* @__PURE__ */ e.jsx("a", { href: f(`rental/units/${n.id}/edit`), style: { fontWeight: 600 }, children: n.code }),
              /* @__PURE__ */ e.jsx("div", { style: { color: "var(--muted-foreground)" }, children: [n.product, n.variant].filter(Boolean).join(" · ") }),
              !n.available && /* @__PURE__ */ e.jsx("div", { style: { color: u.late }, children: n.condition === "good" ? "Inactive" : n.condition })
            ] }),
            i.map((c) => /* @__PURE__ */ e.jsx(O, { unit: n, date: c }, c))
          ] }, n.id))
        ] })
      ] }) }),
      m > 1 && /* @__PURE__ */ e.jsxs("div", { style: { display: "flex", gap: 8, justifyContent: "flex-end", alignItems: "center", marginTop: 12 }, children: [
        /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", size: "sm", disabled: r.page <= 1, onClick: () => l({ page: r.page - 1 }), children: "Previous" }),
        /* @__PURE__ */ e.jsxs("span", { children: [
          r.page,
          " / ",
          m
        ] }),
        /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", size: "sm", disabled: r.page >= m, onClick: () => l({ page: r.page + 1 }), children: "Next" })
      ] })
    ] }) })
  ] });
}
const v = { position: "sticky", left: 0, zIndex: 1 };
function b({ color: t, label: r, striped: i = !1 }) {
  return /* @__PURE__ */ e.jsxs("span", { style: { display: "inline-flex", alignItems: "center", gap: 4 }, children: [
    /* @__PURE__ */ e.jsx("span", { style: { width: 12, height: 12, borderRadius: 3, background: i ? T : t } }),
    r
  ] });
}
const T = "repeating-linear-gradient(45deg, var(--muted), var(--muted) 3px, var(--border) 3px, var(--border) 6px)";
function O({ unit: t, date: r }) {
  const i = E(t, r), o = { borderTop: "1px solid var(--border)", borderLeft: "1px solid var(--border)", height: 34, padding: 0 };
  if (i === null) return /* @__PURE__ */ e.jsx("td", { style: { ...o, background: t.available ? void 0 : "var(--muted)" } });
  const { booking: s, rest: d } = i, h = s.kind === "block" ? u.block : u[s.stage ?? "to_pick_up"], x = `${s.label}
${s.start} – ${s.end}${d ? " (rest days)" : ""}`, p = r === s.start, g = /* @__PURE__ */ e.jsx("div", { title: x, style: { height: "100%", background: d ? T : h, color: "#fff", overflow: "hidden", whiteSpace: "nowrap", fontSize: 11, padding: "0 3px", lineHeight: "34px" }, children: p && !d ? s.label : "" });
  return /* @__PURE__ */ e.jsx("td", { style: o, children: s.href && !d ? /* @__PURE__ */ e.jsx("a", { href: s.href, children: g }) : g });
}
B({ pages: { "Rental/Calendar": I } });
