# Materiales de referencia para la generación asistida por IA

Estos archivos alimentan a `App\Services\AiProcedureGenerationService`
cada vez que se crea un procedimiento con el wizard (`processes.store`). Son
fijos: se usan igual para todos los procedimientos nuevos.

Coloca aquí:

1. **`documento_condiciones.docx`** — el Word largo con las condiciones e
   instrucciones de redacción. Dentro de su propio texto hace referencia al
   documento de ejemplo (punto 2) como muestra de cómo debe quedar el
   resultado final.
2. **`documento_ejemplo.docx`** — documento de ejemplo: una referencia de
   cómo debe quedar el resultado final (estructura, tono, nivel de detalle).
   Es al que remite el documento de condiciones.
3. **`texto_validacion.md`** — instrucciones de validación/estilo (el "system
   prompt") que le dicen a la IA cómo debe comportarse al redactar.
4. **`diagrama_ejemplo.png`** — imagen de ejemplo de un diagrama de flujo por
   carriles (uno por puesto/responsable), con óvalos de inicio/fin, pasos
   numerados y rombos de decisión. Se manda a la IA como referencia visual
   (Claude sí puede "ver" imágenes) para que genere el diagrama de cada
   procedimiento nuevo en ese mismo estilo, vía Mermaid + Kroki.io.

Junto con el esqueleto que arma el usuario en el wizard, son las fuentes que
la IA combina para redactar el procedimiento final.

Si falta alguno de estos archivos, `AiProcedureGenerationService` lanzará
un error claro al intentar generar un procedimiento — no hay fallback
silencioso.
