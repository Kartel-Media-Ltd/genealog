/**
 * Tree Visualizer — D3.js v7
 * Force-directed layout with generation constraints.
 * Features: no-overlap, drag nodes, spouse/sibling lines, multi-parent lines.
 */
(function () {
  'use strict';

  const canvas = document.getElementById('tree-canvas');
  if (!canvas) return;

  const treeId = canvas.dataset.treeId;
  if (!treeId) return;

  const NODE_W  = 160;
  const NODE_H  = 80;
  const LEVEL_H = 90; // vertical gap between generations

  fetch('/api/trees/' + treeId + '/persons')
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(renderTree)
    .catch(err => renderError(String(err)));

  // ── Graph helpers ──────────────────────────────────────────────────────────

  function buildGraph(nodes, links) {
    const childrenOf = {};
    nodes.forEach(n => { childrenOf[n.id] = []; });

    // Primary parent edge (from nodes[].parentId)
    nodes.forEach(n => {
      if (n.parentId && childrenOf[n.parentId] !== undefined) {
        childrenOf[n.parentId].push(n.id);
      }
    });

    // Extra parent links from API links[]
    (links || []).filter(l => l.type === 'parent').forEach(l => {
      if (childrenOf[l.source] && !childrenOf[l.source].includes(l.target)) {
        childrenOf[l.source].push(l.target);
      }
    });

    return childrenOf;
  }

  function computeGenerations(nodes, childrenOf) {
    const gen = {};
    const hasParent = new Set(nodes.filter(n => n.parentId).map(n => n.id));

    // BFS from all roots (persons with no parent in tree)
    const queue = nodes.filter(n => !hasParent.has(n.id)).map(n => n.id);
    queue.forEach(id => { gen[id] = 0; });

    const MAX_GEN = 50; // safety valve: no real family tree has 50+ generations
    let qi = 0;
    while (qi < queue.length) {
      const id    = queue[qi++];
      const curG  = gen[id] ?? 0;
      if (curG >= MAX_GEN) continue; // break cycles / excessive depth
      (childrenOf[id] || []).forEach(cid => {
        if (gen[cid] === undefined || gen[cid] < curG + 1) {
          gen[cid] = curG + 1;
          queue.push(cid);
        }
      });
    }

    // Assign gen 0 to any orphans (disconnected from BFS)
    nodes.forEach(n => { if (gen[n.id] === undefined) gen[n.id] = 0; });

    return gen;
  }

  // ── Main render ────────────────────────────────────────────────────────────

  function renderTree({ nodes, links }) {
    if (!nodes || nodes.length === 0) {
      canvas.innerHTML = '<div class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">Brak osób do wyświetlenia.</div>';
      return;
    }

    const childrenOf = buildGraph(nodes, links);
    const genMap     = computeGenerations(nodes, childrenOf);

    // Spouses get same generation (max of both)
    (links || []).filter(l => l.type === 'spouse' || l.type === 'partner').forEach(l => {
      const g = Math.max(genMap[l.source] ?? 0, genMap[l.target] ?? 0);
      genMap[l.source] = g;
      genMap[l.target] = g;
    });

    const W = canvas.clientWidth || 800;

    // Build simulation nodes
    const simNodes = nodes.map((n, i) => ({
      id:   n.id,
      data: n.data,
      gen:  genMap[n.id] ?? 0,
      // spread initial X by index to reduce initial overlap
      x: W / 2 + (i - nodes.length / 2) * (NODE_W + 30),
      y: (genMap[n.id] ?? 0) * LEVEL_H + 60,
    }));

    const byId = {};
    simNodes.forEach(n => { byId[n.id] = n; });

    // Build simulation links with string IDs — D3 forceLink resolves them to objects
    const simLinks = [];

    // Parent-child (tree edge)
    nodes.forEach(n => {
      if (n.parentId && byId[n.parentId]) {
        simLinks.push({ source: n.parentId, target: n.id, kind: 'parent' });
      }
    });
    // Extra parent links
    (links || []).filter(l => l.type === 'parent').forEach(l => {
      if (byId[l.source] && byId[l.target]) {
        simLinks.push({ source: l.source, target: l.target, kind: 'parent-extra' });
      }
    });
    // Spouse / partner / sibling
    (links || []).filter(l => ['spouse', 'partner', 'sibling'].includes(l.type)).forEach(l => {
      if (byId[l.source] && byId[l.target]) {
        simLinks.push({ source: l.source, target: l.target, kind: l.type });
      }
    });

    // ── SVG ──────────────────────────────────────────────────────────────────
    canvas.innerHTML = '';

    const svg = d3.select(canvas)
      .append('svg')
      .attr('width', '100%')
      .attr('height', '100%')
      .style('min-height', '400px');

    const g   = svg.append('g');
    const lg  = g.append('g').attr('class', 'links');
    const ng  = g.append('g').attr('class', 'nodes');

    // Zoom + pan (drag on nodes stops propagation, so no conflict)
    const zoom = d3.zoom()
      .scaleExtent([0.15, 3])
      .on('zoom', event => g.attr('transform', event.transform));
    svg.call(zoom);

    // ── Link drawing ──────────────────────────────────────────────────────────

    // SVG path parser rejects scientific notation (e.g. 1.5e-9) — toFixed prevents it
    const fmt = n => Number.isFinite(n) ? n.toFixed(2) : '0';

    function drawLinks() {
      lg.selectAll('*').remove();

      simLinks.forEach(l => {
        const s = l.source;
        const t = l.target;

        // Skip if forceLink hasn't resolved IDs yet, or if coords are transiently NaN
        if (typeof s !== 'object' || typeof t !== 'object') return;
        if (!isFinite(s.x) || !isFinite(s.y) || !isFinite(t.x) || !isFinite(t.y)) return;
        if (Math.abs(s.x) > 1e6 || Math.abs(s.y) > 1e6 || Math.abs(t.x) > 1e6 || Math.abs(t.y) > 1e6) return;

        if (l.kind === 'parent' || l.kind === 'parent-extra') {
          // Curved vertical line: bottom of parent → top of child
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
          // Horizontal line — use averaged Y so it's always horizontal even if nodes drift
          const avgY = (s.y + t.y) / 2 + NODE_H / 2;
          const left  = s.x <= t.x ? s : t;
          const right = s.x <= t.x ? t : s;
          const x1 = left.x + NODE_W;
          const x2 = right.x;
          // Draw line regardless of overlap — if overlapping, connect centers
          lg.append('line')
            .attr('stroke', 'hsl(var(--primary))')
            .attr('stroke-width', 2)
            .attr('x1', x2 > x1 ? x1 : s.x + NODE_W / 2)
            .attr('y1', avgY)
            .attr('x2', x2 > x1 ? x2 : t.x + NODE_W / 2)
            .attr('y2', avgY);

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
      .attr('width', NODE_W)
      .attr('height', NODE_H)
      .attr('x', d => d.x)
      .attr('y', d => d.y);

    foSel.append('xhtml:div')
      .style('width', NODE_W + 'px')
      .style('height', NODE_H + 'px')
      .style('display', 'flex')
      .style('align-items', 'center')
      .style('gap', '8px')
      .style('padding', '8px')
      .style('border-radius', '8px')
      .style('border', '1px solid hsl(var(--border))')
      .style('background', 'hsl(var(--card))')
      .style('box-shadow', '0 1px 3px rgba(0,0,0,.1)')
      .style('overflow', 'hidden')
      .style('user-select', 'none')
      .style('cursor', 'grab')
      .html(d => nodeHtml(d.data));

    // ── Drag ─────────────────────────────────────────────────────────────────
    // Attach drag to foreignObject (not the div — events on div don't reach SVG)

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
        // Snap Y to nearest generation row on release
        const nearestGen = Math.max(0, Math.round((d.y - 60) / LEVEL_H));
        d.y   = nearestGen * LEVEL_H + 60;
        d.gen = nearestGen;
        d3.select(this).attr('y', d.y);
        drawLinks();
      });

    foSel.call(drag);

    // ── Force simulation (layout only — runs to convergence, then stops) ─────

    // Collect spouse pairs for custom force
    const spouseLinks = simLinks.filter(l => l.kind === 'spouse' || l.kind === 'partner');

    const SPOUSE_GAP = 20; // px gap between two spouse cards

    const simulation = d3.forceSimulation(simNodes)
      // Strong Y force: pins each node to its generation row
      .force('genY', d3.forceY(d => d.gen * LEVEL_H + 60).strength(0.95))
      // Weak X force: center horizontally
      .force('centerX', d3.forceX(W / 2).strength(0.03))
      // X-only repulsion among nodes of the SAME generation — prevents horizontal overlap
      // without pushing nodes off their Y row (unlike forceCollide which is 2D)
      .force('xRepel', function (alpha) {
        const byGen = {};
        simNodes.forEach(n => {
          if (!byGen[n.gen]) byGen[n.gen] = [];
          byGen[n.gen].push(n);
        });
        Object.values(byGen).forEach(group => {
          for (let i = 0; i < group.length - 1; i++) {
            for (let j = i + 1; j < group.length; j++) {
              const a = group[i];
              const b = group[j];
              const dx     = b.x - a.x;
              const minDx  = NODE_W + 20; // minimum gap: card width + 20px
              const absDx  = Math.abs(dx);
              if (absDx < minDx) {
                const push = (minDx - absDx) * 0.4 * alpha;
                const dir  = dx === 0 ? (Math.random() > 0.5 ? 1 : -1) : (dx > 0 ? 1 : -1);
                a.vx -= push * dir;
                b.vx += push * dir;
              }
            }
          }
        });
      })
      // Link forces
      .force('links', d3.forceLink(simLinks)
        .id(d => d.id)  // resolve string IDs to node objects
        .distance(l => {
          if (l.kind === 'spouse' || l.kind === 'partner') return NODE_W + SPOUSE_GAP;
          if (l.kind === 'sibling') return NODE_W + 30;
          return LEVEL_H;
        })
        .strength(l => {
          if (l.kind === 'spouse' || l.kind === 'partner') return 1.0;
          if (l.kind === 'sibling') return 0.3;
          if (l.kind === 'parent' || l.kind === 'parent-extra') return 0.2;
          return 0.1;
        })
      )
      // Custom force: equalise Y for each spouse pair — velocity only (never touch .y directly)
      .force('spouseY', function (alpha) {
        spouseLinks.forEach(l => {
          const a = l.source;
          const b = l.target;
          const dy = b.y - a.y;
          a.vy += dy * alpha * 0.4;
          b.vy -= dy * alpha * 0.4;
        });
      })
      .alphaDecay(0.03)
      .on('tick', () => {
        foSel.attr('x', d => d.x).attr('y', d => d.y);
        drawLinks();
      })
      .on('end', () => {
        // Hard-set spouse pairs to identical Y and exact side-by-side X
        spouseLinks.forEach(l => {
          const a = l.source;
          const b = l.target;
          const avgY = (a.y + b.y) / 2;
          a.y = avgY;
          b.y = avgY;
          // Place left/right based on current X order
          const left  = a.x <= b.x ? a : b;
          const right = a.x <= b.x ? b : a;
          const midX  = (left.x + right.x + NODE_W) / 2;
          left.x  = midX - NODE_W - SPOUSE_GAP / 2;
          right.x = midX + SPOUSE_GAP / 2;
        });
        foSel.attr('x', d => d.x).attr('y', d => d.y);
        drawLinks();

        // Center view (both X and Y)
        let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
        simNodes.forEach(n => {
          if (n.x < minX) minX = n.x;
          if (n.x + NODE_W > maxX) maxX = n.x + NODE_W;
          if (n.y < minY) minY = n.y;
          if (n.y + NODE_H > maxY) maxY = n.y + NODE_H;
        });
        const cx = (minX + maxX) / 2;
        const cy = (minY + maxY) / 2;
        const H  = canvas.clientHeight || 600;
        // maxX/maxY already include NODE_W/NODE_H — no double-count
        const treeW = maxX - minX;
        const treeH = maxY - minY;
        // Scale down if tree is larger than canvas (with 10% padding)
        const scaleX = treeW > W * 0.9 ? (W * 0.9) / treeW : 1;
        const scaleY = treeH > H * 0.9 ? (H * 0.9) / treeH : 1;
        const scale  = Math.min(scaleX, scaleY, 1);
        svg.call(zoom.transform, d3.zoomIdentity
          .translate(W / 2, H / 2)
          .scale(scale)
          .translate(-cx, -cy));
      });

    // Initial draw while simulation warms up
    drawLinks();
  }

  // ── Node HTML ─────────────────────────────────────────────────────────────

  function nodeHtml(data) {
    const first   = data.firstName || '';
    const last    = data.lastName  || '';
    const initials = (first[0] || '') + (last[0] || '');
    const gColor  = data.gender === 'male'
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

  // ── Fallback (cycles) ─────────────────────────────────────────────────────

  function renderFallback(nodes) {
    canvas.innerHTML = '';
    const wrapper = document.createElement('div');
    wrapper.className = 'p-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 overflow-auto';
    wrapper.style.maxHeight = '560px';

    nodes.forEach(n => {
      const d = n.data;
      const card = document.createElement('a');
      card.href = d.profileUrl;
      card.className = 'flex items-center gap-2 rounded-md border border-border bg-card p-3 hover:bg-accent transition-colors text-sm';
      const initials = ((d.firstName || '')[0] + (d.lastName || '')[0]).toUpperCase();
      card.innerHTML = `
        <div class="h-8 w-8 rounded-full bg-muted flex items-center justify-center text-xs font-semibold flex-shrink-0">${escHtml(initials)}</div>
        <div class="min-w-0">
          <div class="font-medium truncate">${escHtml((d.firstName || '') + ' ' + (d.lastName || ''))}</div>
          ${d.birthYear ? `<div class="text-xs text-muted-foreground">ur. ${d.birthYear}</div>` : ''}
        </div>`;
      wrapper.appendChild(card);
    });

    canvas.appendChild(wrapper);
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
