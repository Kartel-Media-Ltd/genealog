/**
 * print-helper.js — Eksport SVG jako PNG
 * Genealog — client-side, zero zależności
 */

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

    var svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
    var url = URL.createObjectURL(svgBlob);

    var img = new Image();

    img.onload = function () {
        var vb = svgElement.viewBox && svgElement.viewBox.baseVal;
        var srcW = (vb && vb.width)  || svgElement.width.baseVal.value  || 2480;
        var srcH = (vb && vb.height) || svgElement.height.baseVal.value || 1754;

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

        var link = document.createElement('a');
        link.download = filename || 'drzewo.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    };

    img.onerror = function () {
        URL.revokeObjectURL(url);
        alert('Błąd eksportu PNG. Użyj opcji "Drukuj" i wybierz "Zapisz jako PDF" jako alternatywę.');
    };

    img.src = url;
}
