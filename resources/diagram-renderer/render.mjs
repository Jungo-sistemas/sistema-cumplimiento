// Rasteriza a PNG un SVG ya pintado por FlowDiagramSvgPainter (colores/insignias/carriles ya
// inyectados — este script NUNCA decide estilo, solo toma la foto). Reutiliza el mismo Chromium
// que ya trae instalado @mermaid-js/mermaid-cli (mismo `headless: "shell"` que usa su propio
// código, ver node_modules/@mermaid-js/mermaid-cli/src/index.js) para no depender de una segunda
// descarga de navegador ni de argumentos de lanzamiento sin probar en este servidor.
//
// Uso: node render.mjs <ruta-svg-entrada> <ruta-png-salida>
// Invocado desde PHP con proc_open() — NUNCA con Illuminate\Support\Facades\Process, que hace
// tronar a Node en este servidor Windows con "Assertion failed: ncrypto::CSPRNG" (ver
// AiProcedureGenerationService::runMermaidCli() para el mismo problema ya documentado).

import puppeteer from "puppeteer";
import path from "path";
import { pathToFileURL } from "url";

const [, , inputSvgPath, outputPngPath, scaleArg] = process.argv;
const scale = scaleArg ? parseFloat(scaleArg) : 1;

if (!inputSvgPath || !outputPngPath || !Number.isFinite(scale) || scale <= 0) {
  console.error("Uso: node render.mjs <entrada.svg> <salida.png> [factor-de-escala]");
  process.exit(1);
}

(async () => {
  const browser = await puppeteer.launch({ headless: "shell" });

  try {
    const page = await browser.newPage();
    const fileUrl = pathToFileURL(path.resolve(inputSvgPath)).href;
    await page.goto(fileUrl, { waitUntil: "load" });

    const svgHandle = await page.$("svg");
    if (!svgHandle) {
      throw new Error("El archivo de entrada no tiene un elemento <svg> en la raíz.");
    }

    const box = await svgHandle.boundingBox();
    if (!box || box.width <= 0 || box.height <= 0) {
      throw new Error("No se pudo medir el tamaño del SVG.");
    }

    // deviceScaleFactor: el SVG es vectorial (su viewBox no cambia con esto), pero la captura de
    // pantalla SÍ es un raster — sin subir este factor, el PNG final sale a 1 pixel físico por
    // punto CSS y se ve borroso/pixelado en cuanto Word lo encoge o alguien hace zoom (el "-s" de
    // mermaid-cli ya no sirve para esto: confirmado que no cambia el viewBox del SVG en absoluto,
    // solo afectaba la resolución cuando mermaid-cli exportaba PNG directo).
    await page.setViewport({
      width: Math.ceil(box.width),
      height: Math.ceil(box.height),
      deviceScaleFactor: scale,
    });

    await svgHandle.screenshot({ path: outputPngPath, omitBackground: false });
  } finally {
    await browser.close();
  }
})().catch((err) => {
  console.error(err?.message ?? String(err));
  process.exit(1);
});
