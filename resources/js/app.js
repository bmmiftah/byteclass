import './bootstrap';
import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import { daftarDenah } from './denah';
import { daftarKelompok } from './kelompok';

window.Alpine = Alpine;
window.QRCode = QRCode;

daftarDenah(Alpine); 
daftarKelompok(Alpine);	
// harus sebelum Alpine.start()
Alpine.start();