(function(){
  var map, layer, polyline;
  var BASE = location.origin + '/mapcloud-mini-prova/backend';
  function toast(msg){
    var t=document.getElementById('toast');
    if(!t) return; 
    t.textContent=msg;
    t.hidden=false;
    setTimeout(function(){ if(t) t.hidden=true },3000);
  }
  function loading(v){
    var el = document.getElementById('loading');
    if(!el) return; 
    el.hidden = !v;
  }
  function initMap(){
    map = L.map('map').setView([-23.55,-46.63], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'\u00a9 OpenStreetMap'}).addTo(map);
    layer = L.layerGroup().addTo(map);
    polyline = null;
    fetchKPIs();
    fetchEntregas(); // Carrega a lista inicial
  }
  function clearMap(){
    layer.clearLayers();
    if(polyline){map.removeLayer(polyline);polyline=null}
  }
  function plot(lat,lng,label){
    if(lat===null||lng===null) return;
    var nlat = parseFloat(lat), nlng = parseFloat(lng);
    if(isNaN(nlat)||isNaN(nlng)) return;
    L.marker([nlat,nlng]).addTo(layer).bindPopup(label||'');
  }
  function fetchJSON(url){return fetch(url).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})}

  function fetchEntregas(page) {
    page = page || 1;
    loading(true);
    fetchJSON(BASE + '/api_entregas.php?page=' + page + '&limit=10')
      .then(function(res) {
        renderListaEntregas(res.data || []);
        renderPaginacao(res.total || 0, page, 10);
      })
      .catch(function(e) {
        console.error('Falha ao buscar entregas', e);
        toast('Não foi possível carregar a lista de entregas.');
      })
      .finally(function() {
        loading(false);
      });
  }

  function renderListaEntregas(entregas) {
    var tbody = document.getElementById('lista-entregas');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (entregas.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Nenhuma entrega encontrada.</td></tr>';
      return;
    }
    entregas.forEach(function(entrega) {
      var tr = document.createElement('tr');
      tr.style.cursor = 'pointer';
      tr.dataset.chave = entrega.chave;

      var statusClass = 'status-' + (entrega.status_atual || 'RECEBIDA').replace(/ /g, '_').toUpperCase();
      
      tr.innerHTML = 
        '<td>' + entrega.id + '</td>' +
        '<td class="chave-col">' + entrega.chave + '</td>' +
        '<td class="status-col ' + statusClass + '">' + (entrega.status_atual || 'RECEBIDA') + '</td>' +
        '<td><button class="copy-btn" title="Copiar Chave">&#x1f4cb;</button></td>';
      
      tbody.appendChild(tr);
    });
  }

  function renderPaginacao(total, currentPage, limit) {
    var pagEl = document.getElementById('paginacao');
    if (!pagEl) return;
    pagEl.innerHTML = '';
    var totalPages = Math.ceil(total / limit);
    if (totalPages <= 1) return;

    for (var i = 1; i <= totalPages; i++) {
      var btn = document.createElement('button');
      btn.textContent = i;
      btn.dataset.page = i;
      if (i === currentPage) {
        btn.className = 'active';
      }
      pagEl.appendChild(btn);
    }
  }

  function handleTableClick(e) {
    var target = e.target;
    var tr = target.closest('tr');
    if (!tr || !tr.dataset.chave) return;

    var chave = tr.dataset.chave;

    if (target.classList.contains('copy-btn')) {
      navigator.clipboard.writeText(chave).then(function() {
        toast('Chave copiada!');
      }, function() {
        toast('Falha ao copiar.');
      });
    } else {
      document.getElementById('inpChave').value = chave;
      buscar();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }

  function handlePaginationClick(e) {
    if (e.target.tagName === 'BUTTON' && e.target.dataset.page) {
      var page = parseInt(e.target.dataset.page, 10);
      fetchEntregas(page);
    }
  }

  function fetchKPIs(){
    fetchJSON(BASE + '/api_metricas_gargalo.php')
    .then(function(g){
      var total = g.total_entregas || 0;
      var delivered = g.counts && g.counts.ENTREGUE ? g.counts.ENTREGUE : 0;
      var pct = total > 0 ? Math.round((delivered / total) * 100) : 0;
      
      document.getElementById('kpiTotal').textContent = total;
      document.getElementById('kpiPct').textContent = pct + '%';
      document.getElementById('kpiGargalo').textContent = g.gargalo || '-';
    })
    .catch(function(e){
      console.warn('KPIs failed', e);
      document.getElementById('kpiTotal').textContent = '-';
      document.getElementById('kpiPct').textContent = '-';
      document.getElementById('kpiGargalo').textContent = '-';
    });
  }

  function buscar(){
    var chave = document.getElementById('inpChave').value.trim();
    if(!chave){toast('Informe a chave');return}
    loading(true);
    fetchJSON(BASE + '/api_rastreamento.php?chave='+encodeURIComponent(chave))
    .then(function(data){
      renderTimeline(data);
      renderMapa(data);
    })
    .catch(function(e){console.error(e);toast('Falha ao buscar')})
    .finally(function(){loading(false)});
  }

  function renderTimeline(data){
    var el = document.getElementById('timeline');
    if(!data){el.innerHTML='Nenhum dado';return}
    var entrega = data.entrega || null;
    var eventos = data.eventos || [];

    // Map statuses to steps
    var steps = [
      {key:'NF',label:'NF‑e',found:null},
      {key:'ROMANEIO',label:'Romaneio',found:null},
      {key:'CTE',label:'CT‑e',found:null},
      {key:'ENTREGA',label:'Entrega',found:null}
    ];

    function findEventByPatterns(patterns){
      patterns = patterns||[];
      for(var i=0;i<eventos.length;i++){
        var s = (eventos[i].status||'').toUpperCase();
        for(var j=0;j<patterns.length;j++){
          if(s.indexOf(patterns[j])!==-1) return eventos[i];
        }
      }
      return null;
    }

    steps[0].found = entrega? {data_hora: entrega.data_emissao, status:'NFE_RECEBIDA'} : findEventByPatterns(['NFE']);
    steps[1].found = findEventByPatterns(['ROMANEIO']);
    steps[2].found = findEventByPatterns(['CTE','CT-E','EM_TRANSITO']);
    steps[3].found = findEventByPatterns(['ENTREGUE','DEVOLVIDA']);

    // render html
    var html = '<div class="timeline-steps">';
    for(var k=0;k<steps.length;k++){
      var s = steps[k];
      var cls = s.found? 'step done' : 'step';
      var when = s.found? (s.found.data_hora || '-') : '-';
      var st = s.found? (s.found.status || '') : '';
      html += '<div class="step-box '+cls+'">'
        + '<h4>'+s.label+'</h4>'
        + '<p class="when">'+when+'</p>'
        + '<p class="status">'+st+'</p>'
        + '</div>';
    }
    html += '</div>';

    // fallback: also show raw events for debugging
    html += '<hr/><div class="events"><h3>Eventos</h3><ul>';
    eventos.forEach(function(ev){
      html += '<li>['+(ev.data_hora||'')+'] '+(ev.status||'')+' '+(ev.observacao?'- '+ev.observacao:'')+'</li>';
    });
    html += '</ul></div>';
    el.innerHTML = html;
  }

  function renderMapa(data){
    clearMap();
    var latLngs = [];
    if(data && data.entrega){
      var e = data.entrega;
      if(e.dest_lat && e.dest_lng){
        plot(e.dest_lat, e.dest_lng, 'Destino: '+(e.destinatario_nome||''));
        map.setView([parseFloat(e.dest_lat), parseFloat(e.dest_lng)], 12);
      }
    }
    if(data && data.eventos){
      data.eventos.forEach(function(ev){
        if(ev.lat && ev.lng){
          var nlat = parseFloat(ev.lat), nlng = parseFloat(ev.lng);
          if(!isNaN(nlat) && !isNaN(nlng)){
            latLngs.push([nlat,nlng]);
            L.circleMarker([nlat,nlng],{radius:5,fill:true,color:'#2d7ef7'}).addTo(layer).bindPopup(ev.status||'');
          }
        }
      });
      if(latLngs.length>1){
        polyline = L.polyline(latLngs,{color:'#2d7ef7'}).addTo(map);
        map.fitBounds(polyline.getBounds(),{padding:[40,40]});
      }
    }
  }
  document.getElementById('btnBuscar').addEventListener('click', buscar);
  document.getElementById('btnTopo').addEventListener('click', function(){window.scrollTo({top:0,behavior:'smooth'})});
  document.getElementById('lista-entregas').addEventListener('click', handleTableClick);
  document.getElementById('paginacao').addEventListener('click', handlePaginationClick);
  window.addEventListener('load', initMap);
})();
