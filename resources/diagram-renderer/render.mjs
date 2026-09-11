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

const [, , inputSvgPath, outputPngPath] = process.argv;

if (!inputSvgPath || !outputPngPath) {
  console.error("Uso: node render.mjs <entrada.svg> <salida.png>");
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

    await page.setViewport({
      width: Math.ceil(box.width),
      height: Math.ceil(box.height),
    });

    await svgHandle.screenshot({ path: outputPngPath, omitBackground: false });
  } finally {
    await browser.close();
  }
})().catch((err) => {
  console.error(err?.message ?? String(err));
  process.exit(1);
});
