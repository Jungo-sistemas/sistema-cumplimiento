# Procesos — cómo funciona el área

Guía para entender qué hace el área de Procesos de Jungo Cumple, cómo está armada en el código y qué
reglas no hay que romper. Está escrita para quien va a darle soporte, programar en ella o revisar un
problema en producción. Refleja el estado del código a noviembre de 2026.

---

## 1. Qué es Procesos, en una frase

Es el lugar donde cada empresa del grupo guarda sus **documentos de procesos** (procedimientos,
políticas, instructivos, formatos y registros), los **redacta o actualiza** (a mano o con ayuda de
IA), los **manda a aprobar** por una cadena de puestos (Líder → Jefe → Gerente → Dirección/Consejo)
y lleva el control de su **versión vigente y su vencimiento** (un año desde que se aprueba).

En el código, un "proceso" se llama **`Regulation`** (reglamento) por razones históricas. La URL es
`/processes`. Son lo mismo.

---

## 2. Vocabulario

| Término en pantalla | En el código | Qué es |
|---|---|---|
| Proceso / Procedimiento / Reglamento | `Regulation` (tabla `regulations`) | La ficha del documento: código, nombre, empresa, tipo, estado de aprobación. |
| Versión | `RegulationVersion` (tabla `regulation_versions`) | Cada archivo que ha tenido el proceso (v1, v2…). Solo una es la **vigente** (`is_current`). |
| Anexo | `Regulation` con `is_annex = true` | Un documento de apoyo (formato, registro) que se liga a uno o varios procesos. Es un proceso más, con su propio código. |
| Ligar anexos | tabla `regulation_annexes` | Relación "este proceso usa estos anexos". |
| Carpeta | `ProcessType` (tabla `process_types`) | Las 14 áreas del cliente (01 Dirección … 14 Seguridad). Dentro de cada empresa, los procesos se ven por carpeta. |
| Tipo de documento | `regulations.document_type` | Procedimiento, Política, Instructivo, Formato o Registro. |
| Puesto | `JobPosition` (tabla `job_positions`, pivote `user_job_positions`) | Líder, Jefe, Gerente, Dirección, Consejo. Define quién aprueba. Un usuario puede tener varios. |
| Responsable | tabla `regulation_responsables` | Los operativos que pueden editar ese proceso. Quien lo crea queda como responsable. |
| Nivel de impacto (del flujo) | `regulations.impact_level` | bajo, medio, medio_alto, alto. **Decide cuántos pasos tiene la aprobación.** |
| Impacto para evaluación | `regulations.evaluation_impact` | bajo, medio, alto. No tiene nada que ver con el flujo: es para definir cada cuánto se audita. |
| Síntesis de cambios | `regulations.change_summary_*` + `RegulationChangeEvidence` | Archivo (y hasta 3 evidencias) que el admin adjunta al mandar a aprobar, para que los aprobadores sepan qué cambió. |

---

## 3. Quién puede hacer qué

### Roles (el rol del usuario)

- **superadmin**: entra a todo, de todos los grupos.
- **admin**: administra su grupo (o las empresas que tenga asignadas).
- **operative** (operativo): trabaja en su(s) empresa(s).
- **readonly** (solo lectura): solo consulta.

### Acceso por área (`users.module_access`)

Cada usuario tiene `all` (Ambos), `procesos` (Solo Procesos) o `cumplimiento` (Solo Cumplimiento).
Lo que importa en Procesos:

| Función en `User` | Quién | Para qué se usa |
|---|---|---|
| `hasProcesosGestion()` | Superadmin, o alguien con Gestión (admin, auditor u operativo) **y** acceso a Procesos | Ver la Gestión de Procesos. Sin esto, la persona solo ve **Mi espacio**. |
| `isProcessAdmin()` | Superadmin, o admin con acceso a Procesos | Botones de admin: asignar flujo, aprobar sin flujo, papelera, obsoleto, cargar. |
| `isProcessEditor()` | Admin de Procesos u operativo con acceso a Procesos | Crear, editar y compartir procesos. |
| `isAuditeeOnly()` | Quien no tiene Gestión | Toda la app es "Mi espacio" para él. |

**Regla de edición** (`Regulation::isEditableBy()`):

1. Si el proceso está **a mitad del flujo** (`pending_review` o `pending_authorization`), **nadie lo
   edita, ni un admin**. Es lo que los aprobadores están revisando.
2. Hay que tener Gestión de Procesos y acceso a la empresa del proceso.
3. Un admin edita cualquiera de sus empresas. Un operativo **solo si es responsable**; si no lo es,
   puede "Solicitar acceso" y a los admins les llega el aviso.

> Caso real (P-SAV-019, 19-nov-2026): una admin no podía editar un proceso. Sus permisos estaban
> bien; el proceso estaba "En revisión". Primero se aprueba o se aprueba sin flujo, y luego se edita.

### Empresas que ve cada quien

- Sin empresa asignada = ve **todas** las del grupo.
- Con una o varias empresas = solo esas (`accessibleCompanyIds()`, `canAccessCompany()`).
- Las empresas marcadas como "otras" (gasolineras externas) no aparecen en Procesos.

### La carpeta confidencial "DIRECCIÓN"

- Es una **empresa** especial por grupo (`companies.is_restricted = true`), que sale primero en la
  lista de empresas y adentro tiene las mismas 14 carpetas.
- **Solo la ven** quienes tienen el puesto **Consejo** y los admins **Solo Procesos**. **No** la ven
  Dirección, los admins de "Ambos" ni el superadmin. Así lo pidió el cliente.
- Para los demás, esos procesos **no existen**: se ocultan con un *global scope* en `Regulation`
  y en `Company`. Abrirlos por URL da 404.
- Su flujo siempre es un solo paso: **Consejo**, sin importar el impacto (`RESTRICTED_FLOW`).
- No tienen QR ni enlace público, y solo se comparten con quien puede verlos.

> **Cuidado al programar:** una consulta con `DB::table('regulations')` se salta el filtro
> confidencial. Usa Eloquent (`Regulation::...`). Sin usuario logueado (colas, comandos) no se
> filtra nada.

---

## 4. Estados de un proceso

### Estado de aprobación (`regulations.approval_status`)

| Valor | En pantalla | Significa |
|---|---|---|
| `null` | Sin flujo | Recién creado o cargado; un admin todavía no le asigna flujo. |
| `pending_review` | En revisión | Flujo arrancado, esperando el primer paso. |
| `pending_authorization` | En autorización | Ya pasó al menos un paso; faltan los siguientes. |
| `approved` | Aprobado | Terminó el flujo, o un admin lo aprobó sin flujo, o se cargó ya aprobado. |
| `rejected` | Rechazado | Alguien del flujo lo rechazó. Se corrige y el admin lo reenvía. |
| `outdated` | Desactualizado | Documento viejo cargado solo de referencia. **No se descarga, no entra a flujo, no genera recordatorios.** Se sustituye con una versión nueva o se manda a Obsoleto. |

### Vigencia (semáforo, `statusColor()` / `statusLabel()`)

La vigencia vive en la **versión** (`regulation_versions.valid_until`), no en el proceso.

- Se asigna **un año** desde el momento en que el flujo termina en aprobado.
- "Aprobar sin flujo" solo la pone si no tenía (no reinicia el año).
- Un proceso nuevo no tiene vigencia hasta que se aprueba → "Pendiente de aprobación" (amarillo).
- Colores: verde = Vigente · amarillo = Por vencer (≤ 60 días) o pendiente · rojo = Vencido ·
  azul = aprobado sin fecha · gris = Desactualizado.

> El campo del wizard que se llama `fecha_vigencia` **es la fecha de elaboración**, no el
> vencimiento. El nombre quedó de antes.

### Otros lugares donde puede estar un proceso

- **Papelera** (`deleted_at`, soft delete): un admin lo manda desde la lista. Desde la papelera se
  restaura o se borra definitivamente a mano. Al mandarlo se le pone `permanently_delete_at` a 2
  meses, **pero hoy ningún comando lo purga solo** (el `documents:purge-trash` diario solo limpia
  Documentos generales, no Procesos).
- **Obsoleto** (`obsoleted_at`): solo los **Desactualizados** pueden ir ahí. Se pueden regresar a
  Procedimientos, salvo que ya se hayan reemplazado por una versión nueva (`replaced_by_id`).

---

## 5. Códigos únicos

- El código (p. ej. `P-SAV-019`, `F-SAV-083`) se guarda en MAYÚSCULAS y sin espacios sobrantes
  (`Regulation::normalizeCode()`).
- Es **único dentro del grupo entre los documentos vivos**. Los de la papelera y los de Obsoleto no
  cuentan. Lo cuida el índice parcial `regulations_group_code_unique` y la regla
  `Regulation::uniqueCodeRule()` / `codeTaken()`.
- Para **liberar un código** basta con mandar el proceso a la papelera (o a Obsoleto si es
  Desactualizado). Si después se intenta restaurar y el código ya lo tomó otro, el sistema no lo deja.
- Los anexos también son procesos: **un anexo y un procedimiento no pueden compartir código**.

---

## 6. Cómo nace un proceso (tres caminos)

### A. Crear con IA (wizard) — `processes.create`

1. El usuario llena el wizard: empresa, carpeta, tipo, código, nombre, quién elabora/aprueba, fecha
   de elaboración y las preguntas del "esqueleto" del procedimiento.
2. `RegulationController::previewGenerate()` llama a `AiProcedureGenerationService::generate()`.
   La IA (Claude, modelo `ANTHROPIC_MODEL`) **solo redacta el texto** de 20 campos
   (`DETAIL_FIELDS`) y del documento, más la estructura del diagrama de flujo. Recibe como guía los
   archivos de `resources/ai-reference/` (no están en git, son sensibles; viven solo en disco).
3. El HTML final lo arma **el sistema**, no la IA, para que siempre salga con el mismo formato:
   - `RegulationBodyHtmlBuilder`: el cuerpo, con el estilo exacto del documento de ejemplo.
   - `RegulationDocxHeaderBuilder`: la tabla de encabezado (logo, nombre, código, versión, elaboró,
     aprobó, fecha, página X de Y), igual en todas las hojas.
   - `FlowDiagramLayoutEngine` + `FlowDiagramSvgRenderer`: dibujan el diagrama de flujo por
     carriles (uno por puesto) y se pasa a PNG con Puppeteer. Ya **no** se usa Mermaid ni Kroki.
4. Se muestra una **vista previa paginada** (`processes/preview.blade.php`). El borrador vive en la
   sesión (`ai_procedure_draft`); la base de datos no se toca. Se puede pedir "Solicitar cambios"
   las veces que haga falta (`previewRevise`).
5. Al confirmar (`previewConfirm` → `confirmCreateDraft`) se crea el proceso **sin flujo**
   (`approval_status = null`), su versión 1 en `.docx` (carta, Arial 11), y quien lo creó queda como
   responsable. El Word se llama como el proceso: `NOMBRE_v1.docx`.

### B. Cargar un archivo — `processes.cargar` (solo admins)

- Se sube un PDF, Word, Excel o PowerPoint (máx. 10 MB). Se valida por **extensión**, no por tipo
  detectado, porque en el servidor Windows los Office modernos se detectan como ZIP.
- Se elige **"Ya está aprobado"** (pide vigencia) o **"Enviar a flujo"** (queda sin flujo para que
  le asignen uno).
- El impacto para evaluación es obligatorio (salvo en anexos).
- Opción **"Reemplaza a un documento desactualizado"**: se busca por código, solo entre
  Desactualizados. El viejo se va a **Obsoleto** con `replaced_by_id` y su código queda libre para
  el nuevo. El viejo **no** pasa al historial del nuevo; son dos documentos distintos.

### C. Importación masiva de documentos viejos

`php artisan regulations:import-legacy` carga documentos históricos como **Desactualizados**
(`is_legacy = true`, `approval_status = 'outdated'`), sin flujo ni correos.

---

## 7. Cómo se edita un proceso

Hay cuatro maneras, y todas crean una **versión nueva** (la anterior queda en el historial):

| Botón | Qué hace | Código |
|---|---|---|
| **Editar** (wizard) | Vuelve a redactar el documento con IA a partir de las respuestas del wizard. | `edit` → `previewGenerateEdit` → `confirmEditDraft` |
| **Editar documento** (en línea) | Editor de texto (TipTap) sobre la versión vigente. | `RegulationVersionController::editForm` / `saveEdit` |
| **Subir nueva versión** | Se sube un archivo nuevo. | `RegulationVersionController::store` |
| **Info básica** | Cambia código, nombre, carpeta, responsables, etc. **No** crea versión; reescribe el encabezado del Word (`RegulationDocxHeaderRefresher`). | `editBasic` / `updateBasic` |

### Si el proceso ya estaba Aprobado (o Desactualizado)

Cualquier edición que cambie el contenido **lo regresa a "En revisión"** y reinicia el flujo desde el
paso 1 (`ApprovalFlowService::resubmit()`): el contenido cambió, ya no puede seguir aprobado.

- Si quien edita es **admin**, cae en la pantalla con `?review_flow=1`, donde decide:
  **mantener el flujo** (se avisa a los aprobadores), **cambiarlo**, o **aprobar sin flujo**. Los
  correos a aprobadores esperan a esa decisión.
- Si quien edita es operativo, el flujo se reinicia y se avisa directo.
- La nueva versión conserva la vigencia anterior hasta que se vuelva a aprobar.

### Detalles del editor en línea

- **Candado de edición:** al abrir el editor, la versión queda apartada 30 minutos para esa persona
  (`editing_by`, `editing_expires_at`). Otro usuario ve que alguien la está editando. Se guarda un
  **borrador** automático (`draft_html`).
- **Doble guardado:** guardar tarda (arma el Word y llama a la IA). Si el usuario da clic dos veces,
  el segundo guardado se descarta: se bloquea el proceso y se revisa que la versión siga siendo la
  vigente y siga apartada por él. El botón también se desactiva al primer clic. (Antes esto creaba
  dos versiones con el mismo número: caso P-SAV-019, nov 2026.)
- **Word subido vs. Word del sistema:** si la versión viene de un Word subido por el cliente, al
  editarla **se parcha el .docx anterior** (`DocxLayoutPatcher`) en lugar de reconstruirlo, para no
  perder bordes, viñetas, saltos de página, etc. Si menos del 60% del texto coincide, se reconstruye
  como antes. Los documentos hechos con IA se reconstruyen desde su HTML.
- **Etiquetas:** `@persona` marca a alguien del grupo (solo visual, sin correo; hace que el proceso
  le aparezca en "Mis documentos → Te mencionan"). `#documento` inserta un enlace a otro proceso.
  Los códigos escritos a mano también se convierten en enlace.
- **Qué cambió:** la descripción y la justificación del cambio las escribe el usuario. Con la
  justificación, `RegulationChangeTableService` (modelo barato `ANTHROPIC_CHANGE_MODEL`) arma una
  tabla de "qué se pidió y dónde quedó". Al aprobador se le muestra además un comparativo sección por
  sección sin IA (`RegulationChangeDiffService`). Si la IA falla, se guarda igual.

> **Regla técnica:** todo HTML que se convierta a Word con PhpWord debe pasar antes por
> `AiProcedureGenerationService::sanitizeHtmlForWord()`. PhpWord 1.1 no escapa `<` ni `&` y deja el
> .docx corrupto (síntoma: 500 al Ver/Editar, Word dice "incompatible", pero el registro sí se crea).

---

## 8. El flujo de aprobación

### Los cuatro flujos (`ApprovalFlowService::FLOWS`)

| Impacto | Pasos |
|---|---|
| Bajo | Líder |
| Medio | Líder → Jefe |
| Medio-Alto | Líder → Jefe → Gerente |
| Alto | Líder → Jefe → Gerente → Dirección y Consejo |
| Confidencial (carpeta DIRECCIÓN) | Consejo |

Reglas:

- **Todos aprueban, uno tras otro** (`requires_all`). Dentro de un paso con varias personas, cada una
  recibe la solicitud cuando aprueba la anterior (las demás esperan en `waiting`). En el último paso
  de Alto, primero Dirección y luego Consejo.
- El paso siguiente se abre cuando el anterior termina completo.
- **Si alguien rechaza, todo se detiene:** las pendientes se cancelan, el proceso queda Rechazado y
  se avisa al creador con el motivo.
- Al aprobar el último, queda **Aprobado**, se pone la vigencia de 1 año y se avisa al creador y a
  los aprobadores.

### Asignar flujo (solo admins) — modal "Asignar flujo"

`RegulationController::setFlow()`. El admin elige:

1. **Nivel de impacto** → define los pasos.
2. **Personas por puesto (opcional):**
   - Si **elige personas en algún paso**, los pasos donde **no eligió a nadie se saltan**. Ejemplo:
     solo elige Gerente y Dirección → el flujo arranca en Gerente.
   - Si **no elige a nadie en ningún paso**, entra **todo el puesto** del grupo (todas las personas
     con ese puesto). Ojo: hoy no filtra por empresa.
   - Si ninguno de los elegidos existe ya, se avisa a los admins que falta asignar.
3. **Síntesis de cambios** y hasta 3 evidencias (opcional).
4. O bien **"Aprobado (sin flujo)"**: lo da por aprobado sin pasar por nadie
   (`approveDirectly()`): borra las aprobaciones abiertas y guarda quién y cuándo.
5. Quitar el nivel = "Flujo eliminado" (vuelve a sin flujo).

El modal muestra los puestos en orden de aprobación con "Paso N", busca sin importar acentos y avisa
si nadie tiene asignado un puesto ("Asígnalo en Usuarios").

**Los pasos se congelan al arrancar** (`regulations.flow_steps`). Si después se cambian los FLOWS en
el código, solo afecta a los flujos que se asignen a partir de ese momento.

### Quién decide y dónde

- El aprobador ve sus pendientes en **Mis aprobaciones** (`/my-approvals`) y en la campana. Puede
  aprobar o rechazar (el rechazo pide motivo, máx. 5,000 caracteres).
- El permiso para aprobar es **tener una aprobación pendiente asignada**, aunque no tenga acceso
  general a esa empresa. Un doble clic o una pestaña vieja no da error: regresa a Mis aprobaciones.
- **Rechazado →** se corrige (editando) y el admin da **Reenviar** (`resubmit`), que reinicia desde
  el paso 1. Al editar un rechazado se avisa a los admins que ya está corregido.

### Recordatorios (comando diario 8:00)

`approvals:notify-reminders`: a quien tiene una aprobación pendiente se le recuerda cada 7 días,
hasta 3 veces. Al tercero se escala a los admins de Procesos. Los Desactualizados no generan
recordatorios.

### Si se elimina a un usuario

`resetFlowsForRemovedUser()`: los procesos con flujo abierto donde participaba vuelven a "sin flujo"
para que un admin los reasigne. Los aprobados no se tocan.

---

## 9. Pantallas principales

| Pantalla | Ruta | Qué hay |
|---|---|---|
| Lista de procesos | `/processes` | Primero las tarjetas de empresa (si ve varias), luego las 14 carpetas, y dentro la tabla con filtros por tipo y buscador. Pestañas Procesos / Anexos. Acciones masivas: papelera, obsoleto, reporte Excel. |
| Ficha del proceso | `/processes/{id}` | Info, versión vigente (Ver / Descargar / Editar), historial de versiones, flujo, anexos ligados, compartir, QR. `?open_pdf=1` abre el documento directo. |
| Ver flujo | `/processes/{id}/flow` | Estado de cada paso y quién decidió. |
| Cargar | `/processes/cargar` | Subir un archivo (sección 6B). |
| Obsoleto | `/processes/obsoleto` | Desactualizados retirados. |
| Papelera | `/processes/trash` | Restaurar o borrar definitivamente. |
| Mis aprobaciones | `/my-approvals` | Pendientes del usuario, con lo que cambió. |
| Indicadores de procesos | `processes.dashboard` | Tablero, solo admins. |
| Guías | `processes.guide.*` | Ayuda para el usuario. |
| Mi espacio | `/mi-espacio` | Vista de consulta para quien no tiene Gestión (sección 10). |

**"Ver" un documento:** los generados en el sistema se muestran desde su HTML paginado. Los Word
subidos o parchados se convierten a PDF con LibreOffice (`OfficeDocumentConverter`) para mostrarlos
tal cual. Si LibreOffice falla, se ofrece descargar.

---

## 10. Mi espacio (vista de consulta)

Para quien no tiene Gestión de Procesos (solo lectura, o admins/operativos "Solo Cumplimiento"):

- **Mis documentos:** siempre muestra la última versión **aprobada** (`publishedVersion`,
  `regulation_versions.approved_at`), aunque haya una versión nueva en revisión. Pestañas "Te
  mencionan" (donde lo etiquetaron con `@persona`) y "Procesos de mi empresa" (por carpetas).
- Admins y auditores pueden cambiar entre Gestión y Mi espacio.

---

## 11. Compartir, QR y anexos

- **Compartir** (`RegulationShareController`): solo procesos **aprobados**. Se elige a personas del
  grupo; les llega un correo con un enlace personal que registra cuándo lo abrieron. Compartir le da
  acceso a **ese** documento aunque sea de otra empresa.
- **QR de pared:** enlace público permanente (`public_share_token`) que siempre abre la versión
  vigente, sin iniciar sesión (`/p/{id}/{token}`). No aplica a confidenciales.
- **Anexos:** se ligan desde la ficha (`setAnnexes`, buscador `searchAnnexes`). Sus chips abren el
  documento directo.

---

## 12. Correos que manda Procesos

Todos usan la plantilla `resources/views/emails/layouts/jungo.blade.php` (colores y logo de Jungo
Cumple). Para verlos en local: `/dev/correos` (solo existe con `APP_ENV=local` y sesión iniciada).

Principales: aprobación solicitada, aprobado, rechazado, recordatorio y escalamiento, listo para
reenviar, paso sin asignar, solicitud de acceso y acceso concedido, documento compartido.

Se mandan por la cola. En producción la cola es el servicio Windows **VigiaQueueWorker** (NSSM): si
está detenido, los correos no salen.

---

## 13. Anexos 8 (solicitudes de inversión)

Está junto a Procesos pero **es otra cosa**: `InvestmentRequest` + `InvestmentApprovalService`.

- Cada una de las 26 razones sociales del cliente (`investment_entities`) tiene un **flujo fijo**:
  Gerente → DD → CFO → CEO → Consejero. Todos aprueban, en orden; si uno rechaza se detiene.
- Los flujos se cargan con `php artisan investments:sync-approval-flows` (desde
  `database/seeders/data/investment_approval_flows.php`). Hay que volver a correrlo cuando se den de
  alta las personas que faltan.

---

## 14. Comandos útiles

| Comando | Para qué |
|---|---|
| `approvals:notify-reminders` | Recordatorios de aprobación (diario 8:00). |
| `procesos:revisar-carpetas` | Lista procesos que no quedaron en ninguna carpeta (tipo oculto, sin tipo, etc.). |
| `procesos:revisar-aprobadores [--aplicar]` | Detecta aprobaciones abiertas de personas que el admin no eligió y rearma el flujo. |
| `procesos:ajustar-empresas` | Ajustes de empresa de procesos. |
| `processes:check-requirements [--deep]` | Revisa que estén LibreOffice, Node/Puppeteer, etc. en el servidor. |
| `processes:classify-existing-annexes` | Marca como anexo los documentos que lo son. |
| `regulations:import-legacy` | Carga masiva de documentos viejos como Desactualizados. |
| `regulations:fix-legacy-approval-status` | Corrige el estado de los legados. |
| `fix:accented-uppercase [--dry-run]` | Corrige mayúsculas con acentos mal guardadas. |
| `fix:regulation-version-images`, `fix:upload-elaboration-dates` | Correcciones de datos puntuales. |
| `investments:sync-approval-flows` | Carga los flujos de Anexos 8. |

---

## 15. Archivos clave

```
app/Models/Regulation.php                 Proceso: estados, permisos de edición, códigos, scope confidencial
app/Models/RegulationVersion.php          Versiones, vigencia, candado de edición, menciones
app/Models/RegulationApproval.php         Cada aprobación (paso, persona, estado)
app/Services/ApprovalFlowService.php      Todo el flujo de aprobación
app/Services/AiProcedureGenerationService.php  Redacción con IA + saneador para Word
app/Services/RegulationBodyHtmlBuilder.php     Cuerpo del documento con formato fijo
app/Services/RegulationDocxHeaderBuilder.php   Encabezado del Word
app/Services/FlowDiagramLayoutEngine.php       Diagrama de flujo (geometría)
app/Services/FlowDiagramSvgRenderer.php        Diagrama de flujo (dibujo)
app/Services/DocxLayoutPatcher.php             Editar un Word subido sin romper su formato
app/Services/RegulationChangeDiffService.php   Comparativo de versiones para el aprobador
app/Services/RegulationChangeTableService.php  Tabla "qué se pidió y dónde quedó" (IA)
app/Services/OfficeDocumentConverter.php       Office → PDF con LibreOffice
app/Http/Controllers/RegulationController.php         Lista, ficha, wizard, cargar, flujo, papelera, obsoleto
app/Http/Controllers/RegulationVersionController.php  Editor en línea, subir versión, ver, descargar
app/Http/Controllers/RegulationApprovalController.php Aprobar, rechazar, reenviar
app/Http/Controllers/MyApprovalsController.php        Mis aprobaciones
app/Http/Controllers/RegulationShareController.php    Compartir y QR
app/Http/Controllers/AuditeeController.php            Mi espacio
resources/views/processes/                Vistas de Procesos (index, show, cargar, preview, ...)
resources/views/regulation-versions/edit.blade.php    Editor en línea (TipTap)
resources/ai-reference/                   Documentos guía de la IA (fuera de git)
tests/Feature/                            ApprovalFlowTest, RegulationEditPreservesFormatTest, ...
```

---

## 16. Producción: lo que hay que saber

- Servidor Windows (Azure), XAMPP/Apache como servicio. Proyecto en
  `C:\Users\adminvigia\Documents\GitHub\sistema-cumplimiento`. Base de datos PostgreSQL.
- **Desplegar:** detener Apache y VigiaQueueWorker → `git pull` → `composer install` (si cambió) →
  `npm run build` (siempre que haya cambios en vistas; si no, faltan estilos y hay pantallas que se
  ven vacías) → `php artisan migrate` (si hay) → `optimize:clear`, `optimize`, `view:cache` →
  levantar Apache y la cola.
- El servidor necesita **LibreOffice** (ver documentos), **Node.js** (diagramas con Puppeteer) y la
  llave `ANTHROPIC_API_KEY` en `.env` (`ANTHROPIC_MODEL`, `ANTHROPIC_CHANGE_MODEL`).
- Los archivos de los procesos están en el disco `private`:
  `storage/app/private/regulations/{empresa}/{proceso}/versions/`.
- Para revisar o corregir algo en producción se usa `php artisan tinker --execute="..."`. En
  PowerShell el `$` se escapa con acento grave: `` `$r ``. **Siempre revisar antes de cambiar** y usar
  `Regulation::withoutGlobalScopes()` (tinker no tiene usuario, pero así es explícito).

### Recetas comunes en tinker

- **Aprobar sin flujo** (p. ej. para que se pueda editar):
  `app(App\Services\ApprovalFlowService::class)->approveDirectly($r, $admin)`.
- **Poner Desactualizado:** `$r->update(['approval_status' => 'outdated'])`.
- **Liberar un código:** mandar el proceso a la papelera igual que la pantalla:
  `cancelOpenApprovals($r)`, `permanently_delete_at` a 2 meses y `$r->delete()`. Revisar antes qué
  proceso tiene el código; puede ser un anexo de otro procedimiento.
- **Versión duplicada:** comparar archivo y fecha de ambas antes de borrar; borrar la sobrante y
  dejar `is_current` solo en la buena.
