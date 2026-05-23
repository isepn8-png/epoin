<?php include 'header.php'; ?>
<?php require_once __DIR__ . '/../includes/epoin_security.php'; ?>
<script>window.EPOIN_CSRF = <?= json_encode(epoin_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>

<div class="content-wrapper input-pelanggaran-page"><!-- scope styling -->

  <section class="content-header">
    <h1 class="title-wrap">
      <span class="hero-ico"><i class="fa fa-exclamation-triangle"></i></span>
      <span>Pelanggaran Siswa</span>
      <small class="sub">Input Pelanggaran</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <!-- ===== THEME (Merah) & UX polish ===== -->
  <style>
    :root{
      /* Palet merah (tema pelanggaran) */
      --r900:#7f1d1d; --r800:#991b1b; --r700:#b91c1c; --r600:#dc2626; --r500:#ef4444;
      --r400:#f87171; --r300:#fca5a5; --r200:#fecaca; --r100:#fee2e2; --r50:#fef2f2;
      --rose:#f43f5e; --crimson:#e11d48;
      --ink:#1b2559;

      /* Khusus tabel */
      /* sebelumnya: --row-red: var(--r100);  -> terlalu mencolok
         dibuat jauh lebih soft dengan tint tipis */
      --row-red: rgba(239,68,68,.06); /* <— lembut, hampir putih */
      --thead-red: #b01f1f;
      --thead-red-border: #8e1919;
    }

    .input-pelanggaran-page.content-wrapper{ animation:fadeLift .55s ease-out both; }
    @keyframes fadeLift{0%{opacity:0;transform:translateY(10px)}100%{opacity:1;transform:translateY(0)}}

    .title-wrap{ display:flex; align-items:center; gap:10px; }
    .title-wrap .sub{ color:#64748b; }

    .hero-ico{
      width:40px;height:40px;border-radius:10px;display:inline-grid;place-items:center;
      background:linear-gradient(135deg,var(--r500),var(--rose));
      color:#fff; box-shadow:0 10px 22px rgba(239,68,68,.26);
      animation:popIn .5s ease-out both .05s;
    }
    @keyframes popIn{from{opacity:0;transform:scale(.8) translateY(4px)}to{opacity:1;transform:none}}

    .input-pelanggaran-page .box{ box-shadow:0 10px 26px rgba(239,68,68,.12); border-radius:12px; overflow:hidden; }
    .input-pelanggaran-page .box .box-header{
      border-bottom:0;
      background:linear-gradient(90deg,var(--r50),#fff);
      border-top:3px solid var(--r600);
    }
    .input-pelanggaran-page .box .box-title{ display:flex; align-items:center; gap:8px; margin:0; }
    .input-pelanggaran-page .box-body{ background:linear-gradient(180deg,#fff,var(--r50)); }

    .btn-primary.btn-add-pelanggaran{
      background:linear-gradient(115deg, var(--r500) 0%, var(--r600) 35%, var(--rose) 70%, var(--crimson) 100%);
      border:0; color:#fff!important; border-radius:999px;
      font-weight:800; letter-spacing:.3px;
      padding:14px 22px !important; font-size:16px !important; line-height:1.25;
      display:inline-flex; align-items:center; gap:10px;
      position:relative; isolation:isolate; overflow:hidden;
      box-shadow:0 12px 26px rgba(239,68,68,.30), 0 2px 0 rgba(0,0,0,.06) inset;
      animation: softBounce 2.6s ease-in-out infinite;
      transition:transform .12s ease, box-shadow .2s ease, filter .2s ease;
    }
    .btn-primary.btn-add-pelanggaran i{ font-size:18px; }
    .btn-primary.btn-add-pelanggaran:hover{ transform:translateY(-2px) scale(1.01); box-shadow:0 16px 34px rgba(239,68,68,.38); filter:saturate(1.05); }
    .btn-primary.btn-add-pelanggaran:active{ transform:translateY(0) scale(.98); }
    @keyframes softBounce{ 0%,100%{transform:translateY(0)} 50%{transform:translateY(-3px)} }

    /* ===== Summary cards (lama) — disembunyikan agar hemat ruang, JS tetap pakai id lama ===== */
    .metric-cards{ display:none !important; }

    /* ===== Toolbar + Compact KPI (sebaris) ===== */
    .dt-toolbar{
      border:1px solid var(--r200); border-radius:12px; padding:10px 12px;
      background: linear-gradient(180deg,#fff,var(--r50));
      box-shadow: 0 10px 20px rgba(239,68,68,.08);
      margin-bottom:10px; position:relative; overflow:hidden;
    }

    .toolbar-row{
      display:flex; flex-wrap:wrap; gap:10px;
      justify-content:space-between; align-items:center;
    }

    .toolbar-group{ display:flex; flex-wrap:nowrap; gap:8px; align-items:center; }
    .dt-toolbar .form-control{
      height:34px; padding:6px 10px; font-size:13px;
      border-radius:10px; border:1px solid var(--r200);
    }
    /* Ukuran lebih kecil & sejajar */
    #filterKelas{ flex:0 0 180px; max-width:180px; }
    #filterDate { flex:0 0 220px; max-width:220px; }

    /* Mini KPI pills (tema merah) */
    .toolbar-metrics{ margin-left:auto; display:flex; gap:8px; align-items:center; }
    .kpi-pill{
      display:flex; align-items:center; gap:8px;
      padding:6px 10px; min-height:34px;
      border-radius:999px; border:1px solid var(--r200);
      background:linear-gradient(180deg,#fff,var(--r50));
      box-shadow:0 4px 10px rgba(239,68,68,.10), inset 0 -1px 0 rgba(239,68,68,.08);
    }
    .kpi-icon{
      width:22px; height:22px; border-radius:50%;
      display:grid; place-items:center;
      background:radial-gradient(closest-side,var(--r300),var(--r100));
      box-shadow:inset 0 0 0 1px rgba(239,68,68,.25);
    }
    .kpi-icon i{ color:var(--r700); font-size:12px; line-height:1; }
    .kpi-text{ display:flex; flex-direction:column; line-height:1.05; }
    .kpi-label{
      font-size:10px; text-transform:uppercase; letter-spacing:.35px;
      color:#0f172a;            /* <- hitam/gelap agar terbaca */
      font-weight:700;
    }
    .kpi-value{ font-size:16px; font-weight:800; color:#7f1d1d; }

    @media (max-width:768px){
      .title-wrap .sub{ display:none; }
      .btn-group.pull-right{ float:none!important; width:100%; margin-top:10px; }
      .btn-group.pull-right .btn{ width:100%; }
      .btn-primary.btn-add-pelanggaran{ padding:12px 16px !important; font-size:15px !important; }

      .toolbar-group{ flex-wrap:wrap; }
      .dt-toolbar .form-control{ min-width:1px; flex:1 1 100%; }
      .toolbar-row{ gap:8px; }
      .toolbar-metrics{ width:100%; order:2; flex-wrap:wrap; justify-content:space-between; }
      .kpi-pill{ flex:1 1 calc(50% - 6px); }
      #filterKelas,#filterDate{ flex:1 1 180px; max-width:none; }
    }

    @keyframes shimmer{ 0%{ background-position:-400px 0 } 100%{ background-position:400px 0 } }
    .dt-toolbar.shimmer::after{
      content:""; position:absolute; inset:0;
      background-image: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,.6), rgba(255,255,255,0));
      background-size: 400px 100%; animation: shimmer 1.2s ease-in-out 1; pointer-events:none;
    }

    #table-datatable{ border-collapse:separate; border-spacing:0; width:100%; }
    #table-datatable thead th{
      background: var(--thead-red) !important;
      color:#fff !important;
      border-bottom:2px solid var(--thead-red-border) !important;
      text-transform:uppercase; letter-spacing:.25px; font-weight:800;
      position:relative; padding:6px 8px; font-size:13px; line-height:1.2;
      background-image:none !important;
    }
    @media (max-width:768px){ #table-datatable thead th{ font-size:12px; padding:6px 6px; } }

    #table-datatable thead th.sorting,
    #table-datatable thead th.sorting_asc,
    #table-datatable thead th.sorting_desc{ padding-right:20px !important; }
    #table-datatable thead th.sorting_asc::before,
    #table-datatable thead th.sorting::before{
      content:""; position:absolute; right:8px; top:6px; border:5px solid transparent; border-bottom-color:#fff; opacity:.95;
    }
    #table-datatable thead th.sorting_desc::after,
    #table-datatable thead th.sorting::after{
      content:""; position:absolute; right:8px; top:16px; border:5px solid transparent; border-top-color:#fff; opacity:.95;
    }

    #table-datatable.table>tbody>tr>td{ border-bottom:1px solid rgba(239,68,68,.22)!important; transition:background .25s ease, box-shadow .25s ease, transform .06s ease; position:relative; padding:8px 10px; font-size:13.5px; }
    #table-datatable.table>tbody>tr:nth-child(odd)>td{ background: var(--row-red) !important; }
    #table-datatable.table>tbody>tr:nth-child(even)>td{ background: #fff !important; }
    #table-datatable.table>tbody>tr:hover>td{ background: linear-gradient(90deg, rgba(239,68,68,.10), rgba(239,68,68,0) 60%), inherit !important; box-shadow:inset 0 0 0 9999px rgba(239,68,68,.02); transform:translateY(-1px); }
    #table-datatable.table>tbody>tr:hover>td:first-child::before{ content:""; position:absolute; left:-1px; top:-1px; bottom:-1px; width:6px; background:linear-gradient(180deg,var(--r600),var(--rose)); border-radius:4px; }

    #table-datatable thead th.col-nama{ white-space:nowrap; }
    #table-datatable th.col-nama, #table-datatable td.col-nama{ min-width:150px; }
    #table-datatable thead th.col-ta{ white-space:nowrap; }
    #table-datatable th.col-ta, #table-datatable td.col-ta{ min-width:110px; }
    #table-datatable thead th.col-pelanggaran{ white-space:nowrap; }
    #table-datatable td.col-pelanggaran{ max-width:640px; white-space:normal; word-wrap:break-word; }

    #table-datatable thead th.col-opsi{ white-space:nowrap; }
    #table-datatable th.col-opsi, #table-datatable td.col-opsi{ min-width:140px; }

    .badge-point-red{
      display:inline-block; min-width:60px; text-align:center;
      background:linear-gradient(90deg,var(--r200),var(--r500));
      color:#7f1d1d; border-radius:999px; padding:3px 10px; font-weight:700;
      box-shadow:inset 0 0 0 1px rgba(239,68,68,.25), 0 2px 8px rgba(239,68,68,.18);
      font-size:12.5px;
    }

    #table-datatable thead th{ position: sticky; top: -1px; z-index: 2; }

    .dt-topbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:6px; }
    .dataTables_length label, .dataTables_filter label{ display:flex; align-items:center; gap:8px; margin:0; font-weight:600; color:#334155; }
    .dataTables_length select{ border-radius:10px; border:1px solid var(--r200); padding:4px 8px; }
    .dataTables_filter input{ border-radius:10px; border:1px solid var(--r200); padding:6px 10px; }

    .btn-warning.btn-sm{ background:linear-gradient(90deg,#f59e0b,#d97706); border-color:#b45309; color:#fff; transition:transform .08s, box-shadow .2s, filter .2s; }
    .btn-warning.btn-sm:hover{ transform:translateY(-1px); box-shadow:0 6px 14px rgba(217,119,6,.25); filter:saturate(1.05); }
    .btn-danger.btn-sm{ background:linear-gradient(90deg,#ef4444,#dc2626); border-color:#b91c1c; color:#fff; transition:transform .08s, box-shadow .2s, filter .2s; }
    .btn-danger.btn-sm:hover{ transform:translateY(-1px); box-shadow:0 6px 14px rgba(220,38,38,.25); filter:saturate(1.05); }

    @media (max-width:768px){
      #table-datatable .btn.btn-sm{ padding:4px 8px; }
      #table-datatable th.col-nama, #table-datatable td.col-nama{ min-width:120px; }
      #table-datatable th.col-ta,   #table-datatable td.col-ta{   min-width:100px; }
      #table-datatable td.col-pelanggaran{ max-width:360px; }
      #table-datatable th.col-opsi, #table-datatable td.col-opsi{ min-width:130px; }
    }
  </style>
  <!-- ===== /THEME ===== -->

  <section class="content">
    <div class="row">
      <section class="col-lg-12">
        <div class="box box-primary">

          <div class="box-header">
            <h3 class="box-title">
              <i class="fa fa-gavel" style="color:var(--r700)"></i> Data Pelanggaran Siswa
            </h3>
            <div class="btn-group pull-right">
              <a href="input_pelanggaran_tambah.php" class="btn btn-primary btn-sm btn-add-pelanggaran" title="Tambah data pelanggaran">
                <i class="fa fa-plus-circle"></i> <span>INPUT PELANGGARAN</span>
              </a>
            </div>
          </div>

          <div class="box-body">

            <!-- ===== Summary cards (Data Tampil & Penerima Hari Ini) — disembunyikan via CSS ===== -->
            <div class="metric-cards">
              <div class="metric-card">
                <div class="mc-label">Data Tampil</div>
                <div class="mc-value" id="mcTotal">0</div>
              </div>
              <div class="metric-card">
                <div class="mc-label">Penerima Poin Hari Ini</div>
                <div class="mc-value" id="mcToday">0</div>
              </div>
            </div>

            <!-- ===== Toolbar filter + KPI compact (SEBARIS) ===== -->
            <div class="dt-toolbar shimmer" id="dtToolbar">
              <div class="toolbar-row">
                <div class="toolbar-group">
                  <select id="filterKelas" class="form-control"><option value="">Semua Kelas</option></select>
                  <input id="filterDate" class="form-control" placeholder="Rentang Tanggal (WAKTU)" autocomplete="off">
                </div>

                <!-- Mini KPI pills (tema merah) -->
                <div class="toolbar-metrics">
                  <div class="kpi-pill" title="Total baris yang tampil">
                    <span class="kpi-icon"><i class="fa fa-table"></i></span>
                    <span class="kpi-text">
                      <span class="kpi-label">Data Tampil</span>
                      <span class="kpi-value" id="mcTotalMini">0</span>
                    </span>
                  </div>
                  <div class="kpi-pill" title="Siswa menerima poin (pelanggaran) hari ini">
                    <span class="kpi-icon"><i class="fa fa-triangle-exclamation"></i></span>
                    <span class="kpi-text">
                      <span class="kpi-label">Poin Hari Ini</span>
                      <span class="kpi-value" id="mcTodayMini">0</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="table-datatable">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>WAKTU</th>
                    <th class="col-nama">NAMA SISWA</th>
                    <th>KELAS</th>
                    <th>TINGKAT</th>
                    <th class="col-ta">TAHUN AJARAN</th>
                    <th class="col-pelanggaran">PELANGGARAN</th>
                    <th>POINT</th>
                    <th class="col-opsi" width="14%">OPSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no=1;
                  $data = mysqli_query($koneksi,"SELECT * FROM siswa, pelanggaran, jurusan, input_pelanggaran, kelas, ta where ta_id=kelas_ta and jurusan_id=kelas_jurusan and input_pelanggaran.kelas=kelas_id and input_pelanggaran.siswa=siswa_id and input_pelanggaran.pelanggaran=pelanggaran_id order by input_pelanggaran.id desc");
                  while($d = mysqli_fetch_array($data)){
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo date('d-m-Y H:i:s', strtotime($d['waktu'])); ?></td>
                      <td class="col-nama"><?php echo epoin_h($d['siswa_nama']); ?></td>
                      <td><?php echo epoin_h($d['kelas_nama']); ?></td>
                      <td><?php echo epoin_h($d['jurusan_nama']); ?></td>
                      <td class="col-ta"><?php echo epoin_h($d['ta_nama']); ?></td>
                      <td class="col-pelanggaran"><?php echo epoin_h($d['pelanggaran_nama']); ?></td>
                      <td class="text-center">
                        <span class="badge-point-red"><?php echo $d['pelanggaran_point']; ?></span>
                      </td>
                      <td class="col-opsi">
                        <a class="btn btn-warning btn-sm" data-toggle="tooltip" title="Edit data" href="input_pelanggaran_edit.php?id=<?php echo $d['id'] ?>">
                          <i class="fa fa-cog"></i>
                        </a>
                        <button type="button" class="btn btn-danger btn-sm pin-guard" data-action="hapus" data-toggle="tooltip" title="Hapus data" data-id="<?php echo (int)$d['id']; ?>">
                          <i class="fa fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                    <?php 
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </section>
    </div>
  </section>

</div>

<!-- Daterangepicker assets (untuk filter tanggal) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<!-- ====== SCRIPT: DataTables init + Filter & Metrics ====== -->
<script>
  $(function () {
    if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }
    var $tbl = $('#table-datatable');

    // Jika sudah ter-init, destroy agar bersih
    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
      try { $tbl.DataTable().destroy(); } catch(e){}
      $tbl.find('thead th').removeClass('sorting sorting_asc sorting_desc sorting_disabled');
    }

    // ===== Inisialisasi DataTable (jika tersedia) =====
    var t = null, hasDT = !!$.fn.DataTable;
    if (!hasDT) {
      console.warn('DataTables tidak terdeteksi — metrik akan dihitung dari DOM.');
    } else {
      t = $tbl.DataTable({
        destroy: true,
        responsive: true,
        autoWidth: false,
        order: [],
        columnDefs: [
          { targets: [0], orderable: false },
          { targets: 0, className: 'dt-center', responsivePriority: 1 },
          { targets: 2, width: '16%', responsivePriority: 1 },
          { targets: 5, width: '9%',  responsivePriority: 3 },
          { targets: 6, width: '34%', responsivePriority: 2 },
          { targets: 3, width: '9%',  responsivePriority: 4 },
          { targets: 4, width: '9%',  responsivePriority: 5 },
          { targets: 8, width: '14%', className: 'dt-center', responsivePriority: 2 },
          {
            targets: 7,
            className: 'text-center',
            type: 'num',
            render: function (data, type) {
              if (type === 'sort' || type === 'type') {
                var m = String(data).replace(/<[^>]*>/g,'').match(/-?\d+/);
                return m ? parseInt(m[0], 10) : 0;
              }
              return data;
            }
          }
        ],
        language: {
          search: "Search:",
          lengthMenu: "Tampilkan _MENU_ data",
          info: "Menampilkan _START_–_END_ dari _TOTAL_ data",
          infoEmpty: "Tidak ada data",
          zeroRecords: "Tidak ditemukan data yang cocok",
          paginate: { first:"Pertama", last:"Terakhir", next:"›", previous:"‹" }
        },
        dom:
          "<'dt-topbar'<'dataTables_length'l><'dataTables_filter'f>>" +
          "<'row'<'col-sm-12'tr>>" +
          "<'row'<'col-sm-5'i><'col-sm-7'p>>"
      });
    }

    // ====== Isi opsi Kelas dari data unik kolom (jika DT aktif) ======
    function fillSelectFromColumn($sel, colIdx){
      if (!t) return;
      var vals = t.column(colIdx).data().unique().toArray()
        .map(v => $('<div>').html(v).text().trim())
        .filter(Boolean)
        .sort((a,b)=>a.localeCompare(b,'id'));
      var current = $sel.val();
      $sel.find('option:not(:first)').remove();
      vals.forEach(v => $sel.append($('<option>',{value:v,text:v})));
      if (vals.includes(current)) $sel.val(current);
    }
    fillSelectFromColumn($('#filterKelas'), 3);

    // ====== Date Range Picker untuk kolom WAKTU ======
    if ($.fn.daterangepicker) {
      $('#filterDate').daterangepicker({
        autoUpdateInput: false,
        timePicker: true,
        timePicker24Hour: true,
        locale: { format: 'DD-MM-YYYY HH:mm:ss', cancelLabel:'Bersihkan', applyLabel:'Terapkan' }
      })
      .on('apply.daterangepicker', function(ev, picker){
        $(this).val(picker.startDate.format('DD-MM-YYYY HH:mm:ss') + ' - ' + picker.endDate.format('DD-MM-YYYY HH:mm:ss'));
        triggerFilter();
      })
      .on('cancel.daterangepicker', function(){
        $(this).val(''); triggerFilter();
      });
    }

    // ====== Logika filter (hanya bila DT aktif) ======
    if (t) {
      $.fn.dataTable.ext.search.push(function(settings, data){
        if (settings.nTable !== $tbl[0]) return true;

        var kelas    = $('#filterKelas').val();
        var waktuStr = data[1];

        var dataKelas = $('<div>').html(data[3]).text().trim();
        if (kelas && (dataKelas !== kelas)) return false;

        var dr = $('#filterDate').val();
        if (dr){
          var parts = dr.split(' - ');
          var start = moment(parts[0], 'DD-MM-YYYY HH:mm:ss');
          var end   = moment(parts[1], 'DD-MM-YYYY HH:mm:ss');
          var cur   = moment(waktuStr, 'DD-MM-YYYY HH:mm:ss');
          if (!cur.isValid() || cur.isBefore(start) || cur.isAfter(end)) return false;
        }
        return true;
      });
    }

    // ====== Apply filter & shimmer ======
    function triggerFilter(){
      $('#dtToolbar').addClass('shimmer');
      setTimeout(()=>$('#dtToolbar').removeClass('shimmer'), 450);
      if (t) t.draw();
      computeMetrics();
    }
    $('#filterKelas').on('change', triggerFilter);

    // ====== METRICS (dengan fallback bila DT tidak ada) ======
    function computeMetrics(){
      var total = 0, todayCount = 0;

      if (t) {
        var rows = t.rows({search:'applied'}).indexes().toArray();
        total = rows.length;

        var allIdx = t.rows().indexes().toArray();
        var todaySet = new Set();
        var today = moment();
        allIdx.forEach(function(idx){
          var waktuStr = $('<div>').html(t.cell(idx,1).data()).text().trim();
          var nama     = $('<div>').html(t.cell(idx,2).data()).text().trim();
          var m = moment(waktuStr, 'DD-MM-YYYY HH:mm:ss');
          if (m.isValid() && m.isSame(today, 'day')) { todaySet.add(nama); }
        });
        todayCount = todaySet.size || 0;
      } else {
        // Fallback DOM murni
        var $rows = $('#table-datatable tbody tr');
        total = $rows.length;
        var today = moment(), set = new Set();
        $rows.each(function(){
          var $td = $(this).children('td');
          var waktuStr = $td.eq(1).text().trim();
          var nama     = $td.eq(2).text().trim();
          var m = moment(waktuStr, 'DD-MM-YYYY HH:mm:ss');
          if (m.isValid() && m.isSame(today,'day')) set.add(nama);
        });
        todayCount = set.size || 0;
      }

      // update elemen lama (disembunyikan) & mini KPI
      $('#mcTotal').text(total);
      $('#mcToday').text(todayCount);
      $('#mcTotalMini').text(total);
      $('#mcTodayMini').text(todayCount);
    }

    // Update metrik saat draw (jika DT aktif)
    if (t) t.on('draw', function(){ computeMetrics(); });

    // Initial
    computeMetrics();

    // ==== Guard tombol Hapus (.pin-guard) + flash sukses ====
    $(document).on('click', '.pin-guard', function(e){
      e.preventDefault();
      var $btn = $(this);
      var id   = parseInt($btn.data('id'), 10) || 0;
      if (!id) return false;

      var $tr   = $btn.closest('tr');
      var nama  = $tr.find('td.col-nama').text().trim();
      var waktu = $tr.find('td:eq(1)').text().trim();

      var pesan = 'Yakin ingin menghapus data pelanggaran'
                + (nama ? (' milik "'+nama+'"') : '')
                + (waktu ? (' pada '+waktu) : '')
                + '?\nTindakan ini tidak dapat dibatalkan.';

      try { $btn.tooltip('hide'); } catch(_){}

      if (confirm(pesan)) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'input_pelanggaran_hapus.php';
        var iId = document.createElement('input');
        iId.type = 'hidden'; iId.name = 'id'; iId.value = String(id);
        form.appendChild(iId);
        if (window.EPOIN_CSRF) {
          var iCsrf = document.createElement('input');
          iCsrf.type = 'hidden'; iCsrf.name = '_csrf'; iCsrf.value = window.EPOIN_CSRF;
          form.appendChild(iCsrf);
        }
        document.body.appendChild(form);
        try { sessionStorage.setItem('flash_msg', 'Data berhasil dihapus.'); } catch(_){}
        form.submit();
      }
      return false;
    });

    // ==== Tampilkan flash message sukses bila ada ====
    (function showFlashIfAny(){
      var msg = null;
      try { msg = sessionStorage.getItem('flash_msg'); } catch(_){}
      if (!msg) return;
      try { sessionStorage.removeItem('flash_msg'); } catch(_){}
      var $alert = $('<div class="alert alert-success alert-dismissible" role="alert" style="margin-bottom:12px;border-radius:10px;">'
                    +'<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
                    + msg + '</div>');
      $('.box .box-body').first().prepend($alert);
      setTimeout(function(){ $alert.fadeOut(350, function(){ $(this).remove(); }); }, 2800);
    })();
  });
</script>

<?php include 'footer.php'; ?>
