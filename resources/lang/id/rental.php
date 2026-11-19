<?php

declare(strict_types=1);

return [
    // Sebutan tempat ambil dress (config commerce-rental.location_term bisa mengubahnya).
    'term' => 'lokasi',
    'errors' => [
        'package' => 'Paket sewa ini sudah tidak tersedia.',
        'package_days' => 'Paket ini untuk :days hari.',
        'dates_missing' => 'Pilih tanggal sewa dan :location untuk setiap dress.',
        'unavailable' => 'Maaf, dress ini sudah dipesan pada tanggal tersebut. Silakan pilih tanggal atau :location lain.',
        'past' => 'Tanggal sewa sudah lewat.',
        'too_long' => 'Sewa paling lama :days hari.',
        'too_far' => 'Tanggal sewa terlalu jauh ke depan.',
        'too_soon' => 'Pesan paling lambat :days hari sebelum tanggal sewa.',
        'too_short' => 'Sewa paling singkat :days hari.',
    ],
    'deposit' => 'DP sekarang',
    'move' => [
        'same_location' => 'Unit ini sudah di :location tersebut.',
        'booked' => '{1} Unit ini masih punya :count booking pelanggan di :location sekarang. Pindahkan setelah dikembalikan, atau ubah booking-nya dulu.|[2,*] Unit ini masih punya :count booking pelanggan di :location sekarang. Pindahkan setelah dikembalikan, atau ubah booking-nya dulu.',
        'done' => ':code dipindah ke :name.',
    ],
    'location' => ['has_units' => ':Location ini masih punya unit sewa. Pindahkan atau hapus unitnya dulu.'],
    'unit' => ['has_bookings' => 'Unit ini punya riwayat pesanan. Nonaktifkan atau tandai Pensiun saja.'],
    'block' => [
        'ends_before' => 'Tanggal selesai sebelum tanggal mulai.',
        'clash' => 'Unit ini sudah terpakai :from – :to.',
    ],
    'line' => ':from – :to (:days hari) · :Location :name',
    'line_one_day' => ':from (1 hari) · :Location :name',
    'form' => [
        'package' => 'Jenis sewa',
        'regular' => 'Reguler',
        'package_option' => ':name: :days hari, tambah :fee',
        'pricing' => 'Harga untuk :days hari. Lebih dari itu tambah :fee per hari.',
        'title' => 'Sewa dress ini',
        'size' => 'Ukuran',
        'start' => 'Tanggal ambil',
        'end' => 'Tanggal kembali',
        'location' => ':Location',
        'choose' => 'Pilih tanggal untuk melihat ketersediaan.',
        'available' => 'Tersedia di :name',
        'unavailable' => 'Tidak tersedia di :name pada tanggal ini',
        'price' => ':days hari: :price',
        'add' => 'Tambah ke keranjang',
        'buy_now' => 'Sewa sekarang',
        'rules' => 'Sewa :min–:max hari. Unit dibersihkan setelah dikembalikan sebelum disewakan lagi.',
    ],
    'conditions' => ['good' => 'baik', 'repair' => 'perlu perbaikan', 'retired' => 'pensiun'],
    'ops' => [
        'not_active' => 'Booking ini sudah tidak aktif.',
        'already_picked_up' => 'Sudah ditandai diambil.',
        'already_returned' => 'Sudah ditandai dikembalikan.',
        'unpaid' => 'Pesanan belum dibayar (DP pun belum).',
        'returned' => 'Ditandai dikembalikan.',
        'returned_clash' => 'Ditandai dikembalikan. Terlambat: masa jeda kini bertabrakan dengan :count booking berikutnya (mulai :date). Ganti unit atau tanggalnya.',
        'dates_changed' => 'Tanggal diubah. Harga tidak berubah: tagih selisihnya lewat ubah pesanan atau link pembayaran.',
        'timeline_picked_up' => 'Unit :unit diambil pelanggan',
        'timeline_returned' => 'Unit :unit dikembalikan, kondisi :condition',
        'timeline_dates' => 'Tanggal sewa diubah dari :old ke :new (unit :unit)',
    ],
    'order' => [
        'locations' => '{1} Ambil dan kembalikan di|[2,*] Ambil dan kembalikan di',
        'whatsapp' => 'Chat :location via WhatsApp',
        'whatsapp_text' => 'Halo, saya mau tanya soal pesanan sewa :number.',
    ],
];
