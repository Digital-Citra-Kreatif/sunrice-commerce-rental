<?php

declare(strict_types=1);

return [
    'errors' => [
        'dates_missing' => 'Pilih tanggal sewa dan cabang untuk setiap dress.',
        'unavailable' => 'Maaf, dress ini sudah dipesan pada tanggal tersebut. Silakan pilih tanggal atau cabang lain.',
        'past' => 'Tanggal sewa sudah lewat.',
        'too_long' => 'Sewa paling lama :days hari.',
        'too_far' => 'Tanggal sewa terlalu jauh ke depan.',
        'too_soon' => 'Pesan paling lambat :days hari sebelum tanggal sewa.',
        'too_short' => 'Sewa paling singkat :days hari.',
    ],
    'deposit' => 'DP sekarang',
    'move' => [
        'same_branch' => 'Unit ini sudah di cabang tersebut.',
        'booked' => '{1} Unit ini masih punya :count booking pelanggan di cabang sekarang. Pindahkan setelah dikembalikan, atau ubah booking-nya dulu.|[2,*] Unit ini masih punya :count booking pelanggan di cabang sekarang. Pindahkan setelah dikembalikan, atau ubah booking-nya dulu.',
        'done' => ':code dipindah ke :branch.',
    ],
    'branch' => ['has_units' => 'Cabang ini masih punya unit. Pindahkan atau hapus unitnya dulu.'],
    'unit' => ['has_bookings' => 'Unit ini punya riwayat pesanan. Nonaktifkan atau tandai Pensiun saja.'],
    'block' => [
        'ends_before' => 'Tanggal selesai sebelum tanggal mulai.',
        'clash' => 'Unit ini sudah terpakai :from – :to.',
    ],
];
