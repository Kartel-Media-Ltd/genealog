/**
 * print-helper.js — Eksport SVG jako PNG
 * Genealog — client-side, zero zależności
 */
'use strict';

/**
 * Rozwiązuje wszystkie odwołania `var(--X)` i `var(--X, fallback)` w stringu SVG
 * na rzeczywiste wartości z `:root`.
 *
 * Bez tego SVG odłączony od arkusza stylów (rasterizacja przez Image)
 * traci kolory CSS custom properties — kolory linii/ramki stają się czarne.
 *
 * Uwaga: zwracane wartości są SUROWĄ wartością property (np. "240 5.9% 90%")
 * a nie pełnym `hsl(...)`. Działa poprawnie tylko gdy `var(--X)` jest opakowane
 * w `hsl(...)` w SVG (np. `stroke="hsl(var(--border))"`). Gołe `color: var(--X)`
 * po podstawieniu da invalid CSS — w obecnym tree-visualizer.js wszystkie
 * odwołania są w `hsl(...)`, więc OK.
 *
 * @param {string} svgString
 * @returns {string}
 */
function resolveCssVariables(svgString) {
    var rootStyle = getComputedStyle(document.documentElement);
    // CSS custom properties są case-sensitive — bez flagi `i`.
    // Drugi non-capturing group obsługuje opcjonalny fallback `var(--X, ...)`.
    return svgString.replace(/var\(--([\w-]+)(?:\s*,\s*[^)]*)?\)/g, function (_match, name) {
        var value = rootStyle.getPropertyValue('--' + name).trim();
        return value || '0 0% 50%'; // szary fallback dla nieznanych zmiennych
    });
}

/**
 * Eksportuje element SVG jako plik PNG.
 * @param {SVGElement|null} svgElement
 * @param {string} filename
 */
function exportSvgAsPng(svgElement, filename) {
    if (!svgElement) {
        window.showModal('Brak drzewa do eksportu. Odczekaj chwilę na załadowanie drzewa i spróbuj ponownie.', 'Eksport niedostępny', 'warning');
        return;
    }

    var serializer = new XMLSerializer();
    var svgData = serializer.serializeToString(svgElement);

    // Upewnij się, że SVG ma deklarację namespace
    if (svgData.indexOf('xmlns') === -1) {
        svgData = svgData.replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"');
    }

    // Rozwiąż zmienne CSS — bez tego linie/ramki będą czarne lub niewidoczne
    svgData = resolveCssVariables(svgData);

    var svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
    var url = URL.createObjectURL(svgBlob);

    var img = new Image();

    img.onload = function () {
        var bbox = svgElement.getBoundingClientRect();
        var vb = svgElement.viewBox && svgElement.viewBox.baseVal;
        var srcW = (vb && vb.width)  || bbox.width  || 2480;
        var srcH = (vb && vb.height) || bbox.height || 1754;

        // Skala 2x dla lepszej jakości (Retina / druk)
        var scale = 2;
        var canvas = document.createElement('canvas');
        canvas.width  = srcW * scale;
        canvas.height = srcH * scale;

        var ctx = canvas.getContext('2d');
        ctx.scale(scale, scale);

        // Białe tło
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, srcW, srcH);

        ctx.drawImage(img, 0, 0, srcW, srcH);

        URL.revokeObjectURL(url);

        // toDataURL może rzucić SecurityError gdy SVG zawiera tainted resources
        // (np. obrazki z innych origin lub niektóre <foreignObject> z HTML).
        var dataUrl;
        try {
            dataUrl = canvas.toDataURL('image/png');
        } catch (err) {
            console.error('Canvas tainted, cannot export PNG:', err);
            window.showModal('Nie można wyeksportować drzewa jako PNG — ograniczenia przeglądarki uniemożliwiają odczyt elementów osadzonych w SVG. Użyj opcji "Drukuj" → "Zapisz jako PDF" jako alternatywę.', 'Eksport PNG niemożliwy', 'warning');
            return;
        }

        // Defensive: niektóre starsze przeglądarki (Firefox) wymagały
        // appendChild przed click() dla programatycznych downloadów.
        var link = document.createElement('a');
        link.download = filename || 'drzewo.png';
        link.href = dataUrl;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    img.onerror = function () {
        URL.revokeObjectURL(url);
        window.showModal('Nie udało się wczytać drzewa do eksportu. Użyj opcji "Drukuj" → "Zapisz jako PDF" jako alternatywę.', 'Błąd eksportu', 'error');
    };

    img.src = url;
}

/**
 * Init: wire up "Drukuj" + "Pobierz PNG" przyciski po DOM ready.
 * Eliminuje race condition (inline onclick mógł zostać kliknięty przed
 * załadowaniem skryptu z `defer`) i unika `unsafe-inline` w CSP.
 */
document.addEventListener('DOMContentLoaded', function () {
    var pngBtn = document.getElementById('btn-export-png');
    if (pngBtn) {
        pngBtn.disabled = false;
        pngBtn.addEventListener('click', function () {
            var svg = document.querySelector('#tree-canvas svg');
            var filename = pngBtn.getAttribute('data-filename') || 'drzewo.png';
            exportSvgAsPng(svg, filename);
        });
    }

    var printBtn = document.getElementById('btn-print');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }
});
