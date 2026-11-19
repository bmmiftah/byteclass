import './bootstrap';
import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import { daftarDenah } from './denah';
import { daftarKelompok } from './kelompok';
import { daftarPenilaian } from './penilaian'; // baru

window.Alpine = Alpine;
window.QRCode = QRCode;

daftarDenah(Alpine);
daftarKelompok(Alpine);
daftarPenilaian(Alpine); // baru

Alpine.start();