# Materiales de referencia para la generación asistida por IA

Estos tres archivos alimentan a `App\Services\AiProcedureGenerationService`
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

Junto con el esqueleto que arma el usuario en el wizard, son las 4 fuentes que
la IA combina para redactar el procedimiento final.

Si falta alguno de los tres archivos, `AiProcedureGenerationService` lanzará
un error claro al intentar generar un procedimiento — no hay fallback
silencioso.
