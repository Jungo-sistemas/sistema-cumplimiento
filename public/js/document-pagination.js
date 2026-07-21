/**
 * Divide un documento HTML de largo variable en hojas tamaño carta (816×1056px @ 96dpi),
 * midiendo la altura real del contenido en el navegador — el largo del documento no se
 * conoce de antemano (viene de un .docx convertido o de una IA), así que no se puede
 * paginar en el servidor.
 *
 * Requiere en el DOM:
 *   <div id="doc-source" style="display:none;">...HTML del documento...</div>
 *   <template id="doc-header-template">...tabla de encabezado fija...</template> (opcional)
 *   <div id="doc-pages"></div>
 *
 * Genera dentro de #doc-pages una serie de <div class="doc-page"> con su encabezado
 * (clonado del template, igual que Word repite la tabla fija en cada página) y su
 * <div class="doc-page-content">.
 */
function paginateDocument() {
    var PAGE_WIDTH = 816;
    var PAGE_HEIGHT = 1056;
    var PAD_TOP = 56, PAD_RIGHT = 72, PAD_BOTTOM = 56, PAD_LEFT = 72;
    var contentWidth = PAGE_WIDTH - PAD_LEFT - PAD_RIGHT;

    var source = document.getElementById('doc-source');
    var container = document.getElementById('doc-pages');
    var headerTemplate = document.getElementById('doc-header-template');
    if (!source || !container) {
        return;
    }

    // La tabla de encabezado es la misma en todas las páginas — se mide una sola vez para
    // saber cuánto espacio reservarle arriba de cada hoja.
    var headerHeight = 0;
    if (headerTemplate) {
        var headerMeasurer = document.createElement('div');
        headerMeasurer.style.position = 'absolute';
        headerMeasurer.style.visibility = 'hidden';
        headerMeasurer.style.left = '-9999px';
        headerMeasurer.style.width = contentWidth + 'px';
        headerMeasurer.appendChild(headerTemplate.content.cloneNode(true));
        document.body.appendChild(headerMeasurer);
        headerHeight = headerMeasurer.scrollHeight;
        headerMeasurer.remove();
    }

    var contentHeight = PAGE_HEIGHT - PAD_TOP - PAD_BOTTOM - headerHeight;

    var measurer = document.createElement('div');
    measurer.className = 'doc-page-content doc-page-measurer';
    measurer.style.position = 'absolute';
    measurer.style.visibility = 'hidden';
    measurer.style.left = '-9999px';
    measurer.style.width = contentWidth + 'px';
    document.body.appendChild(measurer);

    var nodes = Array.prototype.slice.call(source.childNodes);
    var pages = [[]];
    var currentHeight = 0;

    nodes.forEach(function (node) {
        measurer.appendChild(node.cloneNode(true));
        var newHeight = measurer.scrollHeight;

        if (currentHeight > 0 && newHeight > contentHeight) {
            pages.push([node]);
            measurer.innerHTML = '';
            measurer.appendChild(node.cloneNode(true));
            currentHeight = measurer.scrollHeight;
        } else {
            pages[pages.length - 1].push(node);
            currentHeight = newHeight;
        }
    });

    measurer.remove();

    container.innerHTML = '';
    pages.forEach(function (pageNodes, i) {
        var page = document.createElement('div');
        page.className = 'doc-page';
        page.style.width = PAGE_WIDTH + 'px';
        page.style.minHeight = PAGE_HEIGHT + 'px';
        page.style.padding = PAD_TOP + 'px ' + PAD_RIGHT + 'px ' + PAD_BOTTOM + 'px ' + PAD_LEFT + 'px';
        page.style.display = 'flex';
        page.style.flexDirection = 'column';

        if (headerTemplate) {
            var header = headerTemplate.content.cloneNode(true);
            var pageIndicator = header.querySelector('.doc-page-indicator');
            if (pageIndicator) {
                pageIndicator.textContent = (i + 1) + ' de ' + pages.length;
            }
            page.appendChild(header);
        }

        var content = document.createElement('div');
        content.className = 'doc-page-content';
        content.style.flex = '1';
        pageNodes.forEach(function (n) {
            content.appendChild(n);
        });

        page.appendChild(content);
        container.appendChild(page);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    try {
        paginateDocument();
    } catch (e) {
        // Si algo falla al paginar, mostramos el documento completo sin dividir en vez de dejar la vista en blanco.
        console.error('No se pudo paginar el documento, mostrando sin dividir:', e);
        var source = document.getElementById('doc-source');
        var container = document.getElementById('doc-pages');
        var headerTemplate = document.getElementById('doc-header-template');
        if (source && container) {
            var page = document.createElement('div');
            page.className = 'doc-page doc-page-content';
            page.style.width = '816px';
            page.style.padding = '56px 72px';
            if (headerTemplate) {
                page.appendChild(headerTemplate.content.cloneNode(true));
            }
            page.insertAdjacentHTML('beforeend', source.innerHTML);
            container.innerHTML = '';
            container.appendChild(page);
        }
    }
});
