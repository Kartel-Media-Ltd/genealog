/**
 * Tree Visualizer — D3.js v7
 * Static column layout: groups nodes by generation, places spouses adjacent,
 * sorts by parent X to reduce crossings. No force simulation.
 */
(function () {
  'use strict';

  const canvas = document.getElementById('tree-canvas');
  if (!canvas) return;

  const treeId = canvas.dataset.treeId;
  if (!treeId) return;

  const NODE_W     = 160;
  const NODE_H     = 80;
  const NODE_GAP   = 24;   // horizontal gap between unrelated nodes
  const SPOUSE_GAP = 14;   // horizontal gap between spouse/partner cards

  fetch('/api/trees/' + treeId + '/persons')
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(renderTree)
    .catch(err => renderError(String(err)));

  // ── Graph helpers ──────────────────────────────────────────────────────────

  function buildGraph(nodes, links) {
    const childrenOf = {};
    nodes.forEach(n => { childrenOf[n.id] = []; });

    nodes.forEach(n => {
      if (n.parentId && childrenOf[n.parentId] !== undefined) {
        childrenOf[n.parentId].push(n.id);
      }
    });

    (links || []).filter(l => l.type === 'parent').forEach(l => {
      if (childrenOf[l.source] && !childrenOf[l.source].includes(l.target)) {
        childrenOf[l.source].push(l.target);
      }
    });

    return childrenOf;
  }

  // Couple-based generation calculation.
  // 1) Union-find groups spouses/partners into a single "couple" (always same gen).
  // 2) Kahn's topological sort over couples — robust against cycles in dirty data
  //    (e.g. an erroneous "X is parent of Y, Y is parent of X" pair from a bad import).
  // 3) Cycle members are assigned by propagating from any non-cycle ancestor.
  // 4) Each individual inherits their couple's generation.
  function computeGenerations(nodes, childrenOf, links) {
    const uf = {};
    nodes.forEach(n => { uf[n.id] = n.id; });

    function find(x) {
      let root = x;
      while (uf[root] !== root) root = uf[root];
      while (uf[x] !== root) { const nx = uf[x]; uf[x] = root; x = nx; }
      return root;
    }
    function union(a, b) {
      const ra = find(a), rb = find(b);
      if (ra !== rb) uf[ra] = rb;
    }

    (links || []).filter(l => l.type === 'spouse' || l.type === 'partner').forEach(l => {
      if (uf[l.source] !== undefined && uf[l.target] !== undefined) union(l.source, l.target);
    });

    // Couple-level DAG with in-degree + reverse edges
    const coupleChildren = {};
    const coupleParents  = {};
    const inDegree       = {};
    nodes.forEach(n => {
      const c = find(n.id);
      if (!coupleChildren[c]) {
        coupleChildren[c] = new Set();
        coupleParents[c]  = new Set();
        inDegree[c]       = 0;
      }
    });
    Object.entries(childrenOf).forEach(([pId, kids]) => {
      const pc = find(pId);
      kids.forEach(cid => {
        const cc = find(cid);
        if (pc !== cc && !coupleChildren[pc].has(cc)) {
          coupleChildren[pc].add(cc);
          coupleParents[cc].add(pc);
          inDegree[cc]++;
        }
      });
    });

    // Kahn's algorithm — process couples once all their parents are processed
    const coupleGen = {};
    const queue = [];
    Object.keys(coupleChildren).forEach(c => {
      if (inDegree[c] === 0) { coupleGen[c] = 0; queue.push(c); }
    });

    let qi = 0;
    while (qi < queue.length) {
      const c  = queue[qi++];
      const cg = coupleGen[c];
      coupleChildren[c].forEach(child => {
        if (coupleGen[child] === undefined || coupleGen[child] < cg + 1) {
          coupleGen[child] = cg + 1;
        }
        inDegree[child]--;
        if (inDegree[child] === 0) queue.push(child);
      });
    }

    // Cycle members never reach inDegree=0. Assign them iteratively from any
    // non-cycle predecessor (max parent gen + 1). At most O(V) iterations.
    let changed = true, safety = 0;
    while (changed && safety < nodes.length) {
      changed = false; safety++;
      Object.keys(coupleChildren).forEach(c => {
        if (coupleGen[c] !== undefined) return;
        let maxParent = -1;
        coupleParents[c].forEach(p => {
          if (coupleGen[p] !== undefined && coupleGen[p] > maxParent) maxParent = coupleGen[p];
        });
        if (maxParent >= 0) { coupleGen[c] = maxParent + 1; changed = true; }
      });
    }

    // Anything still unassigned (orphan cycle with no external root) → gen 0
    Object.keys(coupleChildren).forEach(c => {
      if (coupleGen[c] === undefined) coupleGen[c] = 0;
    });

    const gen = {};
    nodes.forEach(n => { gen[n.id] = coupleGen[find(n.id)] ?? 0; });
    return gen;
  }

  // ── Static layout ──────────────────────────────────────────────────────────
  // Groups each generation into units (person + optional spouse), distributes
  // evenly, sorts by parent X position to minimize crossing lines.

  function staticLayout(simNodes, simLinks, W, LEVEL_H, childrenOf) {
    // Spouse/partner map: id → id
    const spouseOf = {};
    simLinks.filter(l => l.kind === 'spouse' || l.kind === 'partner').forEach(l => {
      spouseOf[l.source] = l.target;
      spouseOf[l.target] = l.source;
    });

    // Reverse map: child → [parentId, ...]
    const parentOf = {};
    Object.entries(childrenOf).forEach(([pId, kids]) => {
      kids.forEach(cid => {
        if (!parentOf[cid]) parentOf[cid] = [];
        parentOf[cid].push(pId);
      });
    });

    // Group by generation
    const byGen = {};
    simNodes.forEach(n => {
      if (!byGen[n.gen]) byGen[n.gen] = [];
      byGen[n.gen].push(n);
    });

    const gens = Object.keys(byGen).map(Number).sort((a, b) => a - b);

    // centreX[id] = centre X of an already-positioned node (from earlier gens)
    const centreX = {};

    function avgParentX(id) {
      const ps = parentOf[id] || [];
      if (!ps.length) return W / 2;
      let sum = 0, count = 0;
      ps.forEach(pid => {
        if (centreX[pid] !== undefined) { sum += centreX[pid]; count++; }
      });
      return count ? sum / count : W / 2;
    }

    gens.forEach(genNum => {
      const group = byGen[genNum];

      // Sort each row by their parents' average X — children land beneath parents.
      // Tiebreaker (nazwisko / imię / id) zapewnia deterministyczną kolejność dla
      // gen 0 (wszyscy mają W/2) i przy równych X — bez tego sort jest niestabilny
      // i drzewo może się "skakać" przy każdym renderze.
      group.sort((a, b) => {
        const ap = avgParentX(a.id);
        const bp = avgParentX(b.id);
        if (ap !== bp) return ap - bp;
        const aLast  = (a.last_name  || '').toLowerCase();
        const bLast  = (b.last_name  || '').toLowerCase();
        if (aLast !== bLast) return aLast < bLast ? -1 : 1;
        const aFirst = (a.first_name || '').toLowerCase();
        const bFirst = (b.first_name || '').toLowerCase();
        if (aFirst !== bFirst) return aFirst < bFirst ? -1 : 1;
        return a.id < b.id ? -1 : (a.id > b.id ? 1 : 0);
      });

      // Pair spouses together (person immediately followed by their spouse)
      const positioned = new Set();
      const ordered = [];
      group.forEach(n => {
        if (positioned.has(n.id)) return;
        positioned.add(n.id);
        ordered.push(n);
        const spId = spouseOf[n.id];
        if (spId) {
          const sp = group.find(m => m.id === spId && !positioned.has(m.id));
          if (sp) {
            positioned.add(sp.id);
            ordered.push(sp);
          }
        }
      });

      // Compute total row width
      let totalW = ordered.length * NODE_W;
      for (let i = 0; i < ordered.length - 1; i++) {
        const isSpousePair = spouseOf[ordered[i].id] === ordered[i + 1].id;
        totalW += isSpousePair ? SPOUSE_GAP : NODE_GAP;
      }

      // Position nodes left-to-right, centred on W/2
      let curX = W / 2 - totalW / 2;
      ordered.forEach((n, i) => {
        n.x = curX;
        n.y = genNum * LEVEL_H + 60;
        centreX[n.id] = n.x + NODE_W / 2;
        if (i < ordered.length - 1) {
          const isSpousePair = spouseOf[n.id] === ordered[i + 1].id;
          curX += NODE_W + (isSpousePair ? SPOUSE_GAP : NODE_GAP);
        }
      });
    });
  }

  // ── Main render ────────────────────────────────────────────────────────────

  function renderTree({ nodes, links }) {
    if (!nodes || nodes.length === 0) {
      canvas.innerHTML = '<div class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">Brak osób do wyświetlenia.</div>';
      return;
    }

    const childrenOf = buildGraph(nodes, links);
    const genMap     = computeGenerations(nodes, childrenOf, links);

    const W = canvas.clientWidth  || 800;
    const H = canvas.clientHeight || 600;

    // Build node objects
    const simNodes = nodes.map(n => ({
      id:   n.id,
      data: n.data,
      gen:  genMap[n.id] ?? 0,
      x:    0,
      y:    0,
    }));

    const byId = {};
    simNodes.forEach(n => { byId[n.id] = n; });

    // Build link list (string IDs — no forceLink resolution needed)
    const simLinks = [];

    nodes.forEach(n => {
      if (n.parentId && byId[n.parentId]) {
        simLinks.push({ source: n.parentId, target: n.id, kind: 'parent' });
      }
    });

    (links || []).filter(l => l.type === 'parent').forEach(l => {
      if (byId[l.source] && byId[l.target]) {
        simLinks.push({ source: l.source, target: l.target, kind: 'parent-extra' });
      }
    });

    (links || []).filter(l => ['spouse', 'partner', 'sibling'].includes(l.type)).forEach(l => {
      if (byId[l.source] && byId[l.target]) {
        simLinks.push({ source: l.source, target: l.target, kind: l.type });
      }
    });

    // Adaptive LEVEL_H: tree should stay readable at scale >= 0.4
    // At scale 0.4, canvas shows H/0.4 world-pixels; aim to use 85% of that.
    const maxGen = simNodes.reduce((m, n) => Math.max(m, n.gen), 0);
    const availH = (H / 0.4) * 0.85 - 60 - NODE_H;
    const LEVEL_H = Math.max(100, Math.min(180, maxGen > 0 ? Math.floor(availH / maxGen) : 160));

    // Run static layout — sets n.x, n.y for all nodes
    staticLayout(simNodes, simLinks, W, LEVEL_H, childrenOf);

    // ── SVG ──────────────────────────────────────────────────────────────────
    canvas.innerHTML = '';

    const svg = d3.select(canvas)
      .append('svg')
      .attr('width',  '100%')
      .attr('height', '100%')
      .style('min-height', '400px');

    const g  = svg.append('g');
    const lg = g.append('g').attr('class', 'links');
    const ng = g.append('g').attr('class', 'nodes');

    const zoom = d3.zoom()
      .scaleExtent([0.1, 3])
      .on('zoom', event => g.attr('transform', event.transform));
    svg.call(zoom);

    // ── Link drawing ──────────────────────────────────────────────────────────

    const fmt = n => Number.isFinite(n) ? n.toFixed(2) : '0';

    function drawLinks() {
      lg.selectAll('*').remove();

      simLinks.forEach(l => {
        const s = byId[l.source];
        const t = byId[l.target];
        if (!s || !t) return;

        if (l.kind === 'parent' || l.kind === 'parent-extra') {
          const x1 = s.x + NODE_W / 2;
          const y1 = s.y + NODE_H;
          const x2 = t.x + NODE_W / 2;
          const y2 = t.y;
          const cy = (y1 + y2) / 2;
          lg.append('path')
            .attr('fill', 'none')
            .attr('stroke', 'hsl(var(--border))')
            .attr('stroke-width', l.kind === 'parent-extra' ? 1 : 1.5)
            .attr('stroke-dasharray', l.kind === 'parent-extra' ? '5 3' : null)
            .attr('d', `M${fmt(x1)},${fmt(y1)} C${fmt(x1)},${fmt(cy)} ${fmt(x2)},${fmt(cy)} ${fmt(x2)},${fmt(y2)}`);

        } else if (l.kind === 'spouse' || l.kind === 'partner') {
          const midY  = Math.min(s.y, t.y) + NODE_H / 2;
          const left  = s.x <= t.x ? s : t;
          const right = s.x <= t.x ? t : s;
          const x1 = left.x  + NODE_W;
          const x2 = right.x;
          lg.append('line')
            .attr('stroke', 'hsl(var(--primary))')
            .attr('stroke-width', 2)
            .attr('x1', x2 > x1 ? x1 : s.x + NODE_W / 2)
            .attr('y1', midY)
            .attr('x2', x2 > x1 ? x2 : t.x + NODE_W / 2)
            .attr('y2', midY);

        } else if (l.kind === 'sibling') {
          lg.append('line')
            .attr('stroke', 'hsl(var(--muted-foreground))')
            .attr('stroke-width', 1)
            .attr('stroke-dasharray', '4 3')
            .attr('opacity', 0.5)
            .attr('x1', s.x + NODE_W / 2).attr('y1', s.y + NODE_H / 2)
            .attr('x2', t.x + NODE_W / 2).attr('y2', t.y + NODE_H / 2);
        }
      });
    }

    // ── Node cards ────────────────────────────────────────────────────────────

    const foSel = ng.selectAll('foreignObject')
      .data(simNodes)
      .enter()
      .append('foreignObject')
      .attr('width',  NODE_W)
      .attr('height', NODE_H)
      .attr('x', d => d.x)
      .attr('y', d => d.y);

    foSel.append('xhtml:div')
      .style('width',          NODE_W + 'px')
      .style('height',         NODE_H + 'px')
      .style('display',        'flex')
      .style('align-items',    'center')
      .style('gap',            '8px')
      .style('padding',        '8px')
      .style('border-radius',  '8px')
      .style('border',         '1px solid hsl(var(--border))')
      .style('background',     d => d.data.isLiving === false ? 'hsl(0,0%,93%)' : 'hsl(var(--card))')
      .style('box-shadow',     '0 1px 3px rgba(0,0,0,.1)')
      .style('overflow',       'hidden')
      .style('user-select',    'none')
      .style('cursor',         'grab')
      .html(d => nodeHtml(d.data));

    // ── Drag ─────────────────────────────────────────────────────────────────

    let dragged = false;

    const drag = d3.drag()
      .on('start', function () {
        dragged = false;
        d3.select(this).select('div').style('cursor', 'grabbing');
      })
      .on('drag', function (event, d) {
        dragged = true;
        d.x = event.x - NODE_W / 2;
        d.y = event.y - NODE_H / 2;
        d3.select(this).attr('x', d.x).attr('y', d.y);
        drawLinks();
      })
      .on('end', function (event, d) {
        d3.select(this).select('div').style('cursor', 'grab');
        if (!dragged) {
          window.location.href = d.data.profileUrl;
          return;
        }
        // Snap Y to nearest generation row
        const nearestGen = Math.max(0, Math.round((d.y - 60) / LEVEL_H));
        d.y   = nearestGen * LEVEL_H + 60;
        d.gen = nearestGen;
        d3.select(this).attr('y', d.y);
        drawLinks();
      });

    foSel.call(drag);

    // ── Initial draw + fit to canvas ──────────────────────────────────────────

    drawLinks();

    // Compute bounding box and fit/centre tree in canvas
    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    simNodes.forEach(n => {
      if (n.x            < minX) minX = n.x;
      if (n.x + NODE_W   > maxX) maxX = n.x + NODE_W;
      if (n.y            < minY) minY = n.y;
      if (n.y + NODE_H   > maxY) maxY = n.y + NODE_H;
    });

    const treeW = maxX - minX;
    const treeH = maxY - minY;
    const cx    = (minX + maxX) / 2;
    const cy    = (minY + maxY) / 2;

    // Scale so the tree fits with 10% padding; never zoom in (max scale 1)
    const scaleX = treeW > 0 ? (W * 0.90) / treeW : 1;
    const scaleY = treeH > 0 ? (H * 0.90) / treeH : 1;
    const scale  = Math.min(scaleX, scaleY, 1);

    // Correct zoom transform: maps world point (cx,cy) → screen centre (W/2, H/2)
    svg.call(zoom.transform, d3.zoomIdentity
      .scale(scale)
      .translate(W / 2 / scale - cx, H / 2 / scale - cy));
  }

  // ── Node HTML ─────────────────────────────────────────────────────────────

  function nodeHtml(data) {
    const first    = data.firstName || '';
    const last     = data.lastName  || '';
    const initials = (first[0] || '') + (last[0] || '');
    const gColor   = data.gender === 'male'
      ? 'hsl(210,70%,88%)' : data.gender === 'female'
      ? 'hsl(340,60%,88%)' : 'hsl(var(--muted))';

    const avatar = data.photoUrl
      ? `<img src="${escHtml(data.photoUrl)}" alt=""
             style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">`
      : `<div style="width:36px;height:36px;border-radius:50%;background:${gColor};
                     display:flex;align-items:center;justify-content:center;
                     font-size:12px;font-weight:600;flex-shrink:0;">
           ${escHtml(initials.toUpperCase())}
         </div>`;

    const birth = data.birthYear
      ? `<span style="font-size:10px;color:hsl(var(--muted-foreground));">ur. ${data.birthYear}</span>`
      : '';

    return `${avatar}
      <div style="min-width:0;overflow:hidden;">
        <div style="font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                    color:hsl(var(--card-foreground));">${escHtml(first)}</div>
        <div style="font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                    color:hsl(var(--card-foreground));">${escHtml(last)}</div>
        ${birth}
      </div>`;
  }

  // ── Error ─────────────────────────────────────────────────────────────────

  function renderError(msg) {
    canvas.innerHTML = `<div class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
      Błąd ładowania drzewa: ${escHtml(msg)}</div>`;
  }

  // ── Escape ────────────────────────────────────────────────────────────────

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

})();
