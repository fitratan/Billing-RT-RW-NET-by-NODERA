/**
 * ============================================================================
 * MIKHMON THERMAL PRINTER ENGINE (ESC/POS & RAWBT)
 * Universal Driver for 58mm & 80mm POS Printers
 * (Panda, VSC, Iware, Eppos, GOOJPRT, RPP02N, POS-5802, MPT-II, PT-210, etc.)
 * Supports Web Bluetooth (BLE) & RawBT Android Intent (Bluetooth Classic/USB)
 * Features:
 * - Silent Auto-Reconnect via navigator.bluetooth.getDevices() (Connect Once)
 * - ESC/POS Raster Bit Image Monochrome Converter for Logo Printing
 * - Single Voucher / Batch Voucher Thermal Printing
 * ============================================================================
 */
(function(window) {
  'use strict';

  var KNOWN_SERVICES = [
    '000018f0-0000-1000-8000-00805f9b34fb', // Standard POS Print
    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC Transparent Serial
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2', // Vendor Serial
    '0000ae00-0000-1000-8000-00805f9b34fb', // Vendor 2
    '0000ae30-0000-1000-8000-00805f9b34fb', // Vendor 3
    '0000ff00-0000-1000-8000-00805f9b34fb', // Vendor 4
    '0000fee7-0000-1000-8000-00805f9b34fb', // Vendor 5
    '0000ffff-0000-1000-8000-00805f9b34fb'  // Generic
  ];

  var PRINTER_PREFIXES = [
    'POS', 'pos', 'Print', 'print', 'RPP', 'rpp', 'MPT', 'mpt', 'MTP', 'mtp',
    'PT-', 'pt-', 'PT', 'pt', 'VSC', 'vsc', 'Panda', 'panda', 'Iware', 'iware',
    'Eppos', 'eppos', 'Zjiang', 'zjiang', 'GOOJPRT', 'goojprt', 'Thermal', 'thermal',
    'XP-', 'xp-', 'XP', 'JP-', 'JP', 'EP-', 'EP', 'Bluetooth', 'bluetooth',
    'Mobile', 'ESC', 'SPP', '58', '80'
  ];

  function isUserCancelled(err) {
    if (!err) return false;
    var name = err.name || '';
    var msg = (err.message || '').toLowerCase();
    return name === 'NotFoundError' || name === 'AbortError' ||
           msg.indexOf('user cancelled') !== -1 ||
           msg.indexOf('cancelled') !== -1;
  }

  var MikhmonThermal = {
    device: null,
    server: null,
    characteristic: null,
    deviceName: '',
    isConnected: false,
    paperSize: localStorage.getItem('mikhmon_thermal_paper') || '58',
    printMethod: localStorage.getItem('mikhmon_thermal_method') || 'bluetooth',
    templateStyle: localStorage.getItem('mikhmon_thermal_style') || 'classic',
    footerText: localStorage.getItem('mikhmon_thermal_footer') || 'Terima kasih atas pembelian Anda',
    autoCut: localStorage.getItem('mikhmon_thermal_autocut') !== 'no',
    listeners: [],
    _logoCache: {},
    _connectingPromise: null,

    isSupported: function() {
      return (typeof navigator !== 'undefined' && 'bluetooth' in navigator);
    },

    isAndroid: function() {
      return (typeof navigator !== 'undefined' && /Android/i.test(navigator.userAgent));
    },

    addListener: function(fn) {
      if (typeof fn === 'function') {
        this.listeners.push(fn);
      }
    },

    notifyListeners: function(status, msg) {
      for (var i = 0; i < this.listeners.length; i++) {
        try {
          this.listeners[i](status, msg, this);
        } catch (e) {
          console.error('Thermal listener error:', e);
        }
      }
    },

    /**
     * Common device setup & GATT characteristic discovery
     */
    _setupDevice: async function(device) {
      var self = this;
      self.device = device;
      var savedName = localStorage.getItem('mikhmon_thermal_device_name');
      self.deviceName = device.name || savedName || 'Printer Bluetooth (' + (device.id ? device.id.slice(0, 5) : 'POS') + ')';

      // Disconnection listener
      function onDisconnect() {
        self.isConnected = false;
        self.server = null;
        self.characteristic = null;
        // Pertahankan self.device agar ensureConnected() dapat auto-reconnect langsung ke GATT
        self.notifyListeners('disconnected', 'Printer terputus');
      }

      device.removeEventListener('gattserverdisconnected', onDisconnect);
      device.addEventListener('gattserverdisconnected', onDisconnect);

      self.notifyListeners('connecting', 'Menghubungkan ke ' + self.deviceName + '...');
      var server = await device.gatt.connect();
      self.server = server;

      var charFound = null;
      for (var i = 0; i < KNOWN_SERVICES.length; i++) {
        try {
          var service = await server.getPrimaryService(KNOWN_SERVICES[i]);
          if (service) {
            var chars = await service.getCharacteristics();
            for (var j = 0; j < chars.length; j++) {
              var c = chars[j];
              if (c.properties.write || c.properties.writeWithoutResponse) {
                charFound = c;
                break;
              }
            }
            if (charFound) break;
          }
        } catch (err) {}
      }

      // Fallback: iterate all available services
      if (!charFound) {
        try {
          var allServices = await server.getPrimaryServices();
          for (var k = 0; k < allServices.length; k++) {
            try {
              var sChars = await allServices[k].getCharacteristics();
              for (var m = 0; m < sChars.length; m++) {
                var sc = sChars[m];
                if (sc.properties.write || sc.properties.writeWithoutResponse) {
                  charFound = sc;
                  break;
                }
              }
              if (charFound) break;
            } catch (e) {}
          }
        } catch (e) {}
      }

      if (!charFound) {
        throw new Error('Gagal menemukan karakteristik cetak (write) pada printer ini.');
      }

      self.characteristic = charFound;
      self.isConnected = true;

      localStorage.setItem('mikhmon_thermal_device_name', self.deviceName);
      if (device.id) {
        localStorage.setItem('mikhmon_thermal_device_id', device.id);
      }

      self.notifyListeners('connected', 'Terhubung ke ' + self.deviceName);
      return { success: true, name: self.deviceName };
    },

    /**
     * Silent Auto-Reconnect via navigator.bluetooth.getDevices()
     * Eliminates repeated pairing prompts across visits / tabs
     */
    autoConnect: async function() {
      var self = this;
      if (!self.isSupported() || !navigator.bluetooth.getDevices) {
        return false;
      }
      if (self.isConnected && self.characteristic) {
        return true;
      }
      if (self._connectingPromise) {
        return await self._connectingPromise;
      }

      self._connectingPromise = (async function() {
        try {
          var devices = await navigator.bluetooth.getDevices();
          if (!devices || devices.length === 0) {
            return false;
          }
          var savedId = localStorage.getItem('mikhmon_thermal_device_id');
          var targetDevice = null;

          if (savedId) {
            targetDevice = devices.find(function(d) { return d.id === savedId; });
          }
          if (!targetDevice && devices.length === 1) {
            targetDevice = devices[0];
          }

          if (targetDevice) {
            await self._setupDevice(targetDevice);
            return true;
          }
        } catch (err) {
          console.warn('Auto-connect Bluetooth thermal silent failed:', err);
        } finally {
          self._connectingPromise = null;
        }
        return false;
      })();

      return await self._connectingPromise;
    },

    /**
     * Memastikan printer terhubung sebelum mencetak tanpa modal berulang.
     * 1. Jika sudah connected & characteristic siap -> langsung return true.
     * 2. Jika GATT idle/disconnect tapi device masih ada -> reconnect GATT langsung tanpa popup!
     * 3. Jika baru dibuka -> autoConnect() silent via getDevices() tanpa popup!
     * 4. Hanya jika printer baru pertama kali digunakan -> tampilkan modal pairing Chrome.
     */
    ensureConnected: async function() {
      var self = this;
      if (self.isConnected && self.characteristic && self.device && self.device.gatt && self.device.gatt.connected) {
        return true;
      }

      // Reconnect GATT langsung jika objek device tersimpan di memori
      if (self.device && self.device.gatt) {
        try {
          self.notifyListeners('connecting', 'Menghubungkan kembali ke ' + self.deviceName + '...');
          var server = await self.device.gatt.connect();
          if (server) {
            await self._setupDevice(self.device);
            return true;
          }
        } catch (e) {
          console.warn('GATT silent reconnect failed:', e);
        }
      }

      // Coba silent autoConnect via getDevices()
      try {
        var autoOk = await self.autoConnect();
        if (autoOk && self.isConnected && self.characteristic) {
          return true;
        }
      } catch (e) {
        console.warn('Silent autoConnect failed:', e);
      }

      // Fallback: tampilkan modal pairing browser
      var res = await self.connect('printers');
      return Boolean(res && res.success);
    },

    /**
     * Connect to Bluetooth device (shows browser chooser modal)
     * @param {string} mode - 'printers' (default, filtered) or 'all' (acceptAllDevices)
     */
    connect: async function(mode) {
      mode = mode || 'printers';
      var self = this;
      if (!self.isSupported()) {
        throw new Error('Web Bluetooth tidak didukung pada browser ini. Harap gunakan Google Chrome pada Android/PC.');
      }

      try {
        self.notifyListeners('connecting', 'Mencari printer Bluetooth di sekitar...');

        var reqOptions = {};
        if (mode === 'all') {
          reqOptions = {
            acceptAllDevices: true,
            optionalServices: KNOWN_SERVICES
          };
        } else {
          reqOptions = {
            filters: PRINTER_PREFIXES.map(function(pfx) { return { namePrefix: pfx }; }),
            optionalServices: KNOWN_SERVICES
          };
        }

        var device = await navigator.bluetooth.requestDevice(reqOptions);
        if (!device) {
          self.notifyListeners('idle', 'Tidak ada perangkat dipilih');
          return { cancelled: true };
        }

        return await self._setupDevice(device);
      } catch (err) {
        if (isUserCancelled(err)) {
          self.notifyListeners('idle', 'Pemindaian dibatalkan');
          return { cancelled: true };
        }
        self.isConnected = false;
        self.notifyListeners('error', err.message || 'Gagal koneksi Bluetooth');
        throw err;
      }
    },

    disconnect: function() {
      if (this.device && this.device.gatt && this.device.gatt.connected) {
        try {
          this.device.gatt.disconnect();
        } catch (e) {}
      }
      this.isConnected = false;
      this.server = null;
      this.characteristic = null;
      this.notifyListeners('disconnected', 'Printer diputuskan');
    },

    sendRawBytes: async function(bytes) {
      if (!this.isConnected || !this.characteristic) {
        throw new Error('Printer thermal belum terhubung. Silakan sambungkan terlebih dahulu.');
      }

      var chunkSize = 100;
      var uint8 = (bytes instanceof Uint8Array) ? bytes : new Uint8Array(bytes);

      for (var offset = 0; offset < uint8.length; offset += chunkSize) {
        var chunk = uint8.slice(offset, offset + chunkSize);
        if (this.characteristic.writeValueWithoutResponse) {
          await this.characteristic.writeValueWithoutResponse(chunk);
        } else {
          await this.characteristic.writeValue(chunk);
        }
        await new Promise(function(resolve) { setTimeout(resolve, 25); });
      }
      return true;
    },

    /**
     * Kirim data biner ESC/POS via RawBT Intent (Android)
     */
    printRawBT: function(uint8Bytes) {
      var bin = '';
      var bytes = (uint8Bytes instanceof Uint8Array) ? uint8Bytes : new Uint8Array(uint8Bytes);
      for (var i = 0; i < bytes.length; i++) {
        bin += String.fromCharCode(bytes[i]);
      }
      var b64 = window.btoa(bin);
      var intentUrl = 'intent:base64,' + b64 + '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;S.browser_fallback_url=https%3A%2F%2Fplay.google.com%2Fstore%2Fapps%2Fdetails%3Fid%3Dru.a402d.rawbtprinter;end;';

      var link = document.createElement('a');
      link.href = intentUrl;
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      setTimeout(function() {
        if (link.parentNode) link.parentNode.removeChild(link);
      }, 1000);
      return true;
    },

    /**
     * Konversi Gambar Logo ke ESC/POS Monochrome Raster Bit Image (GS v 0)
     * Format: GS v 0 m xL xH yL yH d1...dk
     */
    getEscPosLogoBytes: async function(imageSrc) {
      var self = this;
      if (!imageSrc || typeof imageSrc !== 'string' || imageSrc.trim() === '') {
        return null;
      }
      var cacheKey = imageSrc + '_' + self.paperSize;
      if (self._logoCache[cacheKey]) {
        return self._logoCache[cacheKey];
      }

      return new Promise(function(resolve) {
        var img = new Image();
        img.crossOrigin = 'Anonymous';
        img.onload = function() {
          try {
            var origW = img.naturalWidth || img.width;
            var origH = img.naturalHeight || img.height;
            if (!origW || !origH) {
              resolve(null);
              return;
            }

            // Batasi ukuran logo agar proporsional di thermal
            var maxPrintW = (self.paperSize === '80') ? 480 : 320;
            var maxPrintH = (self.paperSize === '80') ? 160 : 110;

            var scale = 1;
            if (origW > maxPrintW) {
              scale = maxPrintW / origW;
            }
            if ((origH * scale) > maxPrintH) {
              scale = maxPrintH / origH;
            }

            var scaledW = Math.round(origW * scale);
            var scaledH = Math.round(origH * scale);
            if (scaledW < 8) scaledW = 8;
            if (scaledH < 8) scaledH = 8;

            // Lebar raster harus kelipatan 8 dots (1 byte per 8 pixel horizontal)
            var wBytes = Math.ceil(scaledW / 8);
            var widthDots = wBytes * 8;
            var heightDots = scaledH;

            var canvas = document.createElement('canvas');
            canvas.width = widthDots;
            canvas.height = heightDots;
            var ctx = canvas.getContext('2d');

            // Background putih bersih
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, widthDots, heightDots);

            // Gambar di-center
            var offsetX = Math.floor((widthDots - scaledW) / 2);
            ctx.drawImage(img, offsetX, 0, scaledW, scaledH);

            var imgData = ctx.getImageData(0, 0, widthDots, heightDots).data;
            var rasterData = [];
            var hasBlackPixel = false;

            for (var y = 0; y < heightDots; y++) {
              for (var byteIdx = 0; byteIdx < wBytes; byteIdx++) {
                var byteVal = 0;
                for (var bit = 0; bit < 8; bit++) {
                  var x = (byteIdx * 8) + bit;
                  var pixelIdx = (y * widthDots + x) * 4;
                  var r = imgData[pixelIdx];
                  var g = imgData[pixelIdx + 1];
                  var b = imgData[pixelIdx + 2];
                  var a = imgData[pixelIdx + 3];

                  var isBlack = false;
                  // Jika pixel tidak transparan, periksa luminance
                  if (a > 60) {
                    var lum = 0.299 * r + 0.587 * g + 0.114 * b;
                    if (lum < 170) {
                      isBlack = true;
                      hasBlackPixel = true;
                    }
                  }
                  if (isBlack) {
                    byteVal |= (1 << (7 - bit));
                  }
                }
                rasterData.push(byteVal);
              }
            }

            if (!hasBlackPixel) {
              resolve(null);
              return;
            }

            // Perintah ESC/POS Raster: GS v 0 0 xL xH yL yH [data...]
            var xL = wBytes & 0xFF;
            var xH = (wBytes >> 8) & 0xFF;
            var yL = heightDots & 0xFF;
            var yH = (heightDots >> 8) & 0xFF;

            var cmd = [
              0x1B, 0x61, 0x01,                   // ESC a 1 (Center)
              0x1D, 0x76, 0x30, 0x00,             // GS v 0 0 (Raster image)
              xL, xH, yL, yH
            ];

            for (var d = 0; d < rasterData.length; d++) {
              cmd.push(rasterData[d]);
            }
            cmd.push(0x0A); // LF
            cmd.push(0x1B, 0x61, 0x01); // Tetap center

            var resultBytes = new Uint8Array(cmd);
            self._logoCache[cacheKey] = resultBytes;
            resolve(resultBytes);
          } catch (e) {
            console.warn('Gagal memproses bitmap logo:', e);
            resolve(null);
          }
        };
        img.onerror = function(err) {
          console.warn('Gagal memuat file logo:', imageSrc, err);
          resolve(null);
        };
        img.src = imageSrc;
      });
    },

    /**
     * Generate byte ESC/POS untuk Test Print
     * SESUAI REQUEST USER: HANYA SATU KALIMAT SAJA ("sukses terhubung")
     */
    buildTestPrintBytes: function() {
      var encoder = new TextEncoder();
      var textBytes = encoder.encode('sukses terhubung\n\n\n');

      var commands = [
        0x1B, 0x40,             // ESC @: Inisialisasi printer
        0x1B, 0x61, 0x01        // ESC a 1: Center text
      ];

      for (var i = 0; i < textBytes.length; i++) {
        commands.push(textBytes[i]);
      }

      if (this.autoCut) {
        commands.push(0x1D, 0x56, 0x00); // GS V 0: Full cut
      }

      return new Uint8Array(commands);
    },

    /**
     * TEST PRINT SESUAI REQUEST: HANYA SATU KALIMAT SAJA ("sukses terhubung")
     */
    testPrint: async function(forceMethod) {
      var method = forceMethod || this.printMethod;
      if (method === 'rawbt' || (!this.isConnected && this.isAndroid())) {
        return this.testPrintRawBT();
      }
      var connected = await this.ensureConnected();
      if (!connected || !this.isConnected) {
        throw new Error('Printer thermal belum terhubung.');
      }
      var bytes = this.buildTestPrintBytes();
      return await this.sendRawBytes(bytes);
    },

    /**
     * Test print via RawBT Android
     */
    testPrintRawBT: function() {
      var bytes = this.buildTestPrintBytes();
      return this.printRawBT(bytes);
    },

    /**
     * Decode entitas HTML agar tidak mencetak literal &amp; pada struk thermal
     */
    decodeHtml: function(html) {
      if (!html) return '';
      return String(html)
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/&nbsp;/g, ' ');
    },

    padLine: function(left, right, width) {
      left = left || '';
      right = right || '';
      var spaceNeeded = width - left.length - right.length;
      if (spaceNeeded < 1) {
        return left + ' ' + right;
      }
      var spaces = '';
      for (var i = 0; i < spaceNeeded; i++) spaces += ' ';
      return left + spaces + right;
    },

    /**
     * Generate byte ESC/POS untuk Voucher dengan Dukungan Multi-Gaya Template
     * Gaya: 'classic' (Standar), 'compact' (Minimalis Hemat Kertas), 'modern' (Modern Box), 'detailed' (Lengkap Panduan)
     */
    buildVoucherBytes: function(data, logoBytes) {
      data = data || {};
      var self = this;
      var encoder = new TextEncoder();
      var width = (self.paperSize === '80') ? 48 : 32;
      var style = (data.templateStyle || self.templateStyle || 'classic').toLowerCase();

      var sepDash = '';
      for (var s = 0; s < width; s++) sepDash += '-';
      var sepDouble = '';
      for (var d = 0; d < width; d++) sepDouble += '=';

      var cmds = [];
      function add(arr) {
        for (var i = 0; i < arr.length; i++) cmds.push(arr[i]);
      }
      function addText(str) {
        var clean = self.decodeHtml(str);
        var b = encoder.encode(clean);
        for (var i = 0; i < b.length; i++) cmds.push(b[i]);
      }

      var brand = self.decodeHtml(data.hotspotName || data.brandName || '').trim();
      var dns = self.decodeHtml(data.dnsName || '').trim();
      // Hindari duplikasi jika dnsName sama dengan nama hotspot
      var showDns = (dns !== '' && dns.toLowerCase() !== brand.toLowerCase() && ('http://' + dns.toLowerCase()) !== brand.toLowerCase());

      var profile = self.decodeHtml(data.profile || '').trim();
      var validity = self.decodeHtml(data.validity || '').trim();
      var price = self.decodeHtml(data.price || '').trim();
      var code = self.decodeHtml(data.code || data.username || '------').trim();
      var password = self.decodeHtml(data.password || '').trim();
      var isSeparatePass = Boolean(password && password !== code && password !== (data.username || ''));

      var lblPkg = self.decodeHtml(data.lblProfile || 'Paket');
      var lblVal = self.decodeHtml(data.lblValidity || 'Masa Aktif');
      var lblPrc = self.decodeHtml(data.lblPrice || 'Harga');
      var lblCode = self.decodeHtml(data.lblCode || 'KODE VOUCHER');
      var lblPass = self.decodeHtml(data.lblPassword || 'Password');
      var lblWarung = self.decodeHtml(data.lblWarung || 'Warung');
      var footer = self.decodeHtml(data.footerText || self.footerText || data.lblThankYou || '').trim();

      // Inisialisasi ESC/POS
      add([0x1B, 0x40]);

      if (style === 'compact') {
        // ==========================================
        // GAYA 1: COMPACT (MINIMALIS HEMAT KERTAS)
        // ==========================================
        add([0x1B, 0x61, 0x01]); // Center
        if (brand) {
          add([0x1B, 0x45, 0x01]); // Bold
          addText(brand + '\n');
          add([0x1B, 0x45, 0x00]);
        }
        addText(sepDash + '\n');
        addText(lblCode + ':\n');
        add([0x1B, 0x45, 0x01]);
        add([0x1D, 0x21, 0x11]); // Double width & height
        addText(code + '\n');
        add([0x1D, 0x21, 0x00]);
        add([0x1B, 0x45, 0x00]);

        if (isSeparatePass) {
          addText(lblPass + ': ' + password + '\n');
        }

        addText(sepDash + '\n');
        add([0x1B, 0x61, 0x00]); // Left
        var metaLine = profile + (validity ? (' (' + validity + ')') : '');
        addText(self.padLine(metaLine, price, width) + '\n');

        if (showDns) {
          add([0x1B, 0x61, 0x01]);
          addText('Login: ' + dns + '\n');
        }
        addText('\n\n');

      } else if (style === 'modern') {
        // ==========================================
        // GAYA 2: MODERN BOX (BINGKAI ELEGAN)
        // ==========================================
        add([0x1B, 0x61, 0x01]); // Center
        if (logoBytes && logoBytes.length > 0) {
          add(logoBytes);
          add([0x0A]);
        }
        if (brand) {
          add([0x1B, 0x45, 0x01]);
          addText('* ' + brand.toUpperCase() + ' *\n');
          add([0x1B, 0x45, 0x00]);
        }
        if (showDns) {
          addText(dns + '\n');
        }
        addText(sepDouble + '\n');

        add([0x1B, 0x61, 0x00]); // Left
        if (profile) addText(self.padLine(lblPkg + ' :', profile, width) + '\n');
        if (validity) addText(self.padLine(lblVal + ' :', validity, width) + '\n');
        if (price) addText(self.padLine(lblPrc + ' :', price, width) + '\n');

        add([0x1B, 0x61, 0x01]); // Center
        addText(sepDouble + '\n');
        addText(lblCode + '\n');
        add([0x1B, 0x45, 0x01]);
        add([0x1D, 0x21, 0x11]);
        addText(code + '\n');
        add([0x1D, 0x21, 0x00]);
        add([0x1B, 0x45, 0x00]);

        if (isSeparatePass) {
          addText(lblPass + ': ' + password + '\n');
        }

        addText(sepDouble + '\n');
        if (data.warungName) {
          addText(lblWarung + ': ' + self.decodeHtml(data.warungName) + '\n');
        }
        if (footer) {
          addText(footer + '\n');
        }
        addText('\n\n\n');

      } else if (style === 'detailed') {
        // ==========================================
        // GAYA 3: DETAILED (LENGKAP DENGAN PANDUAN)
        // ==========================================
        add([0x1B, 0x61, 0x01]); // Center
        if (logoBytes && logoBytes.length > 0) {
          add(logoBytes);
          add([0x0A]);
        }
        if (brand) {
          add([0x1B, 0x45, 0x01]);
          addText(brand + '\n');
          add([0x1B, 0x45, 0x00]);
        }
        addText('VOUCHER AKSES INTERNET\n');
        addText(sepDash + '\n');

        add([0x1B, 0x61, 0x00]); // Left
        if (profile) addText(self.padLine(lblPkg + ' :', profile, width) + '\n');
        if (validity) addText(self.padLine(lblVal + ' :', validity, width) + '\n');
        if (price) addText(self.padLine(lblPrc + ' :', price, width) + '\n');

        add([0x1B, 0x61, 0x01]); // Center
        addText(sepDash + '\n');
        addText(lblCode + ':\n');
        add([0x1B, 0x45, 0x01]);
        add([0x1D, 0x21, 0x11]);
        addText(code + '\n');
        add([0x1D, 0x21, 0x00]);
        add([0x1B, 0x45, 0x00]);

        if (isSeparatePass) {
          addText(lblPass + ': ' + password + '\n');
        }

        addText(sepDash + '\n');
        add([0x1B, 0x61, 0x00]); // Left
        var howTo = self.decodeHtml(data.lblHowToLogin || 'Cara Login:');
        var step1 = self.decodeHtml(data.lblStepWifi || '1. Sambungkan ke WiFi: ') + ' ' + (brand || 'Hotspot');
        var step2 = self.decodeHtml(data.lblStepBrowser || '2. Buka browser: ') + ' ' + (dns || 'login');
        var step3 = self.decodeHtml(data.lblStepEnterCode || '3. Masukkan kode voucher di atas');

        add([0x1B, 0x45, 0x01]);
        addText(howTo + '\n');
        add([0x1B, 0x45, 0x00]);
        addText(step1 + '\n');
        addText(step2 + '\n');
        addText(step3 + '\n');

        add([0x1B, 0x61, 0x01]); // Center
        addText(sepDash + '\n');
        if (data.warungName) {
          addText(lblWarung + ': ' + self.decodeHtml(data.warungName) + '\n');
        }
        if (footer) {
          addText(footer + '\n');
        }
        addText('\n\n\n');

      } else {
        // ==========================================
        // GAYA 4: CLASSIC / STANDAR (DEFAULT)
        // ==========================================
        add([0x1B, 0x61, 0x01]); // Center
        if (logoBytes && logoBytes.length > 0) {
          add(logoBytes);
          add([0x0A]);
        }
        if (brand) {
          add([0x1B, 0x45, 0x01]);
          addText(brand + '\n');
          add([0x1B, 0x45, 0x00]);
        }
        if (showDns) {
          addText(dns + '\n');
        }
        addText(sepDash + '\n');

        add([0x1B, 0x61, 0x00]); // Left
        if (profile) addText(self.padLine(lblPkg + ' :', profile, width) + '\n');
        if (validity) addText(self.padLine(lblVal + ' :', validity, width) + '\n');
        if (price) addText(self.padLine(lblPrc + ' :', price, width) + '\n');

        add([0x1B, 0x61, 0x01]); // Center
        addText(sepDash + '\n');
        addText(lblCode + ':\n');
        add([0x1B, 0x45, 0x01]);
        add([0x1D, 0x21, 0x11]);
        addText(code + '\n');
        add([0x1D, 0x21, 0x00]);
        add([0x1B, 0x45, 0x00]);

        if (isSeparatePass) {
          addText(lblPass + ': ' + password + '\n');
        }

        addText(sepDash + '\n');
        if (data.warungName) {
          addText(lblWarung + ': ' + self.decodeHtml(data.warungName) + '\n');
        }
        if (footer) {
          addText(footer + '\n');
        }
        addText('\n\n\n');
      }

      if (self.autoCut) {
        add([0x1D, 0x56, 0x00]); // Cut
      }

      return new Uint8Array(cmds);
    },

    /**
     * Cetak Struk Voucher / Transaksi (Otomatis deteksi BLE atau RawBT)
     */
    printVoucher: async function(data, optLogoBytes) {
      var self = this;
      data = data || {};
      var logoBytes = optLogoBytes || null;

      if (!logoBytes && data.logo) {
        try {
          logoBytes = await self.getEscPosLogoBytes(data.logo);
        } catch (e) {
          console.warn('Gagal membaca logo:', e);
        }
      }

      if (self.printMethod === 'rawbt') {
        return self.printVoucherRawBT(data, logoBytes);
      }

      // Pastikan printer tersambung (silent auto-reconnect jika pernah dipasangkan)
      var connected = await self.ensureConnected();
      if (connected && self.isConnected && self.characteristic) {
        var bytes = self.buildVoucherBytes(data, logoBytes);
        return await self.sendRawBytes(bytes);
      }

      if (self.isAndroid()) {
        return self.printVoucherRawBT(data, logoBytes);
      }

      throw new Error('Printer thermal belum terhubung. Silakan sambungkan terlebih dahulu.');
    },

    /**
     * Cetak Voucher via RawBT Android
     */
    printVoucherRawBT: function(data, optLogoBytes) {
      var bytes = this.buildVoucherBytes(data, optLogoBytes);
      return this.printRawBT(bytes);
    }
  };

  // Auto-connect saat DOM selesai dimuat jika perangkat pernah disandingkan
  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
          if (MikhmonThermal.isSupported() && localStorage.getItem('mikhmon_thermal_device_id')) {
            MikhmonThermal.autoConnect();
          }
        }, 300);
      });
    } else {
      setTimeout(function() {
        if (MikhmonThermal.isSupported() && localStorage.getItem('mikhmon_thermal_device_id')) {
          MikhmonThermal.autoConnect();
        }
      }, 300);
    }
  }

  window.MikhmonThermal = MikhmonThermal;
})(window);
