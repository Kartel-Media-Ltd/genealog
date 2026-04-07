/**
 * print-helper.js — Eksport SVG jako PNG
 * Genealog — client-side, zero zależności
 */
'use strict';

/**
 * Rozwiązuje wszystkie odwołania `var(--X)` w stringu SVG na rzeczywiste wartości
 * z `:root`. Bez tego SVG odłączony od arkusza stylów (rasterizacja przez Image)
 * traci kolory CSS custom properties — kolory linii/ramki stają się czarne.
 *
 * @param {string} svgString
 * @returns {string}
 */
function resolveCssVariables(svgString) {
    var rootStyle = getComputedStyle(document.documentElement);
    return svgString.replace(/var\(--([a-z0-9-]+)\)/gi, function (_match, name) {
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
        alert('Brak drzewa do eksportu. Odczekaj chwilę na załadowanie drzewa i spróbuj ponownie.');
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
            alert('Nie można wyeksportować drzewa jako PNG (ograniczenia przeglądarki dla niektórych elementów). Użyj opcji "Drukuj" → "Zapisz jako PDF".');
            return;
        }

        var link = document.createElement('a');
        link.download = filename || 'drzewo.png';
        link.href = dataUrl;
        link.click();
    };

    img.onerror = function () {
        URL.revokeObjectURL(url);
        alert('Nie udało się wczytać drzewa do eksportu. Użyj opcji "Drukuj" → "Zapisz jako PDF" jako alternatywę.');
    };

    img.src = url;
}

/**
 * Init: wire up "Pobierz PNG" button after DOM is ready.
 * Eliminuje race condition (inline onclick mógł zostać kliknięty przed
 * załadowaniem skryptu z `defer`).
 */
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btn-export-png');
    if (!btn) {
        return;
    }
    btn.disabled = false;
    btn.addEventListener('click', function () {
        var svg = document.querySelector('#tree-canvas svg');
        var filename = btn.getAttribute('data-filename') || 'drzewo.png';
        exportSvgAsPng(svg, filename);
    });
});
