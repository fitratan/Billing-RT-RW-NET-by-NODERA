<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {


}
?>
<div class="card">
  <div class="card-header"><h3><i class="fa fa-area-chart"></i> <?= $_traffic_monitor ?> </h3></div>

  <div class="card-body">
    <div class="row">
      <?php 
      $getinterface = $API->comm("/interface/print");
      $getinterface = is_array($getinterface) ? $getinterface : array();
      $TotalReg = count($getinterface);

      $savedIface = $_SESSION[$session . '_iface_name'] ?? '';
      $cfgIface = isset($getinterface[$iface - 1]['name']) ? $getinterface[$iface - 1]['name'] : (isset($getinterface[0]['name']) ? $getinterface[0]['name'] : 'ether1');
      $activeIface = !empty($savedIface) ? $savedIface : $cfgIface;
      ?>
      <div class="col-12">
        <select id="d_interface" class="dropd pd-5" style="max-width: 260px; font-size: 13px; margin-bottom: 12px; cursor: pointer;">
          <option value="" disabled><?= !empty($_select_interface) ? $_select_interface : 'Select Interface'; ?></option>
          <?php 
            for ($i = 0; $i < $TotalReg; $i++) {
              $curName = $getinterface[$i]['name'];
              $sel = ($curName === $activeIface) ? 'selected' : '';
              echo '<option value="' . htmlspecialchars($curName) . '" ' . $sel . '>['.($i+1).'] ' . htmlspecialchars($curName) . '</option>';
            }
          ?>
        </select>
      </div>

      <script type="text/javascript"> 
        var chart;
        var sessiondata = "<?= addslashes($session) ?>";
        var defaultIface = "<?= addslashes($activeIface) ?>";
        var sesKey = "Interface_" + "<?= addslashes($session) ?>";
        var currentIface = sessionStorage.getItem(sesKey);
        if (!currentIface || currentIface === "null" || currentIface === "undefined") {
          currentIface = defaultIface;
          try { sessionStorage.setItem(sesKey, currentIface); } catch(e){}
        }

        $(function(){
          if ($("#d_interface").length) {
            $("#d_interface").val(currentIface);
            $("#d_interface").on("change", function(){
              var newIface = $(this).val();
              if (newIface) {
                currentIface = newIface;
                try { sessionStorage.setItem(sesKey, newIface); } catch(e){}
                if (chart) {
                  chart.setTitle({ text: '<?= $_interface ?> ' + newIface });
                }
                requestDatta(sessiondata, newIface);
              }
            });
          }
        });

        function requestDatta(session, iface) {
          if (!iface || iface === "null" || iface === "undefined") {
            iface = defaultIface;
          }
          $.ajax({
            url: './traffic/traffic.php?session='+encodeURIComponent(session)+'&iface='+encodeURIComponent(iface),
            dataType: "json",
            success: function(data) {
              var midata = (typeof data === "string") ? JSON.parse(data) : data;
              if (midata && midata.length > 0) {
                var TX = parseInt(midata[0].data);
                var RX = parseInt(midata[1].data);
                var x = (new Date()).getTime(); 
                var shift = chart.series[0].data.length > 19;
                chart.series[0].addPoint([x, TX], true, shift);
                chart.series[1].addPoint([x, RX], true, shift);
              }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) { 
              console.error("Traffic error: " + textStatus, errorThrown); 
            }       
          });
        }	

        $(document).ready(function() {
            Highcharts.setOptions({
              global: {
                useUTC: false
              },
              chart: {
                height: 500,
              },
            });

            Highcharts.addEvent(Highcharts.Series, 'afterInit', function () {
              this.symbolUnicode = {
                circle: '●',
                diamond: '♦',
                square: '■',
                triangle: '▲',
                'triangle-down': '▼'
              }[this.symbol] || '●';
            });

            chart = new Highcharts.Chart({
              chart: {
                renderTo: 'trafficMonitor',
                animation: Highcharts.svg,
                type: 'areaspline',
                events: {
                  load: function () {
                    requestDatta(sessiondata, currentIface);
                    chart.setTitle({ text: '<?= $_interface ?> ' + currentIface });
                    setInterval(function () {
                      var ifaceNow = sessionStorage.getItem(sesKey) || currentIface || defaultIface;
                      requestDatta(sessiondata, ifaceNow);
                      chart.setTitle({ text: '<?= $_interface ?> ' + ifaceNow });
                    }, 3000);
                  }				
                }
              },
              title: {
                text: '<?= $_interface ?> ' + currentIface
              },
                        
                        xAxis: {
                          type: 'datetime',
                          tickPixelInterval: 150,
                          maxZoom: 20 * 1000,
                        },
                        yAxis: {
                            minPadding: 0.2,
                            maxPadding: 0.2,
                            title: {
                              text: null
                            },
                            labels: {
                              formatter: function () {      
                                var bytes = this.value;                          
                                var sizes = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
                                if (bytes == 0) return '0 bps';
                                var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
                                return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];                    
                              },
                            },       
                        },
                        
                        series: [{
                          name: 'Tx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }, {
                          name: 'Rx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }],

                        tooltip: {
                          formatter: function () { 
                            var _0x2f7f=["\x70\x6F\x69\x6E\x74\x73","\x79","\x62\x70\x73","\x6B\x62\x70\x73","\x4D\x62\x70\x73","\x47\x62\x70\x73","\x54\x62\x70\x73","\x3C\x73\x70\x61\x6E\x20\x73\x74\x79\x6C\x65\x3D\x22\x63\x6F\x6C\x6F\x72\x3A","\x63\x6F\x6C\x6F\x72","\x73\x65\x72\x69\x65\x73","\x3B\x20\x66\x6F\x6E\x74\x2D\x73\x69\x7A\x65\x3A\x20\x31\x2E\x35\x65\x6D\x3B\x22\x3E","\x73\x79\x6D\x62\x6F\x6C\x55\x6E\x69\x63\x6F\x64\x65","\x3C\x2F\x73\x70\x61\x6E\x3E\x3C\x62\x3E","\x6E\x61\x6D\x65","\x3A\x3C\x2F\x62\x3E\x20\x30\x20\x62\x70\x73","\x70\x75\x73\x68","\x6C\x6F\x67","\x66\x6C\x6F\x6F\x72","\x3A\x3C\x2F\x62\x3E\x20","\x74\x6F\x46\x69\x78\x65\x64","\x70\x6F\x77","\x20","\x65\x61\x63\x68","\x3C\x62\x3E\x4D\x69\x6B\x68\x6D\x6F\x6E\x20\x54\x72\x61\x66\x66\x69\x63\x20\x4D\x6F\x6E\x69\x74\x6F\x72\x3C\x2F\x62\x3E\x3C\x62\x72\x20\x2F\x3E\x3C\x62\x3E\x54\x69\x6D\x65\x3A\x20\x3C\x2F\x62\x3E","\x25\x48\x3A\x25\x4D\x3A\x25\x53","\x78","\x64\x61\x74\x65\x46\x6F\x72\x6D\x61\x74","\x3C\x62\x72\x20\x2F\x3E","\x20\x3C\x62\x72\x2F\x3E\x20","\x6A\x6F\x69\x6E"];var s=[];$[_0x2f7f[22]](this[_0x2f7f[0]],function(_0x3735x2,_0x3735x3){var _0x3735x4=_0x3735x3[_0x2f7f[1]];var _0x3735x5=[_0x2f7f[2],_0x2f7f[3],_0x2f7f[4],_0x2f7f[5],_0x2f7f[6]];if(_0x3735x4== 0){s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[14])};var _0x3735x2=parseInt(Math[_0x2f7f[17]](Math[_0x2f7f[16]](_0x3735x4)/ Math[_0x2f7f[16]](1024)));s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[18]+ parseFloat((_0x3735x4/ Math[_0x2f7f[20]](1024,_0x3735x2))[_0x2f7f[19]](2))+ _0x2f7f[21]+ _0x3735x5[_0x3735x2])});return _0x2f7f[23]+ Highcharts[_0x2f7f[26]](_0x2f7f[24], new Date(this[_0x2f7f[25]]))+ _0x2f7f[27]+ s[_0x2f7f[29]](_0x2f7f[28])
                          },
                          shared: true                                                      
                        },
                      });
                    });
                  </script>
                  <div class="col-12" id="trafficMonitor"></div>
                </div>
              </div>  