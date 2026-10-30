// Editor de procedimientos (regulation-versions/edit.blade.php). Antes se importaba en tiempo de
// ejecución desde esm.sh — cuando ese servicio falla (p. ej. un 500 en una dependencia interna
// como prosemirror-schema-list) el módulo entero no carga y el editor queda en blanco para todos
// los documentos. Empaquetado con Vite no depende de nada externo. Se expone en window porque la
// vista arma el editor en un <script type="module"> en línea (los módulos se ejecutan en orden,
// así que este siempre corre antes).
import { Editor, mergeAttributes, Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Highlight from '@tiptap/extension-highlight';
import Mention from '@tiptap/extension-mention';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import TextStyle from '@tiptap/extension-text-style';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { PluginKey } from '@tiptap/pm/state';

window.Tiptap = {
    Editor, mergeAttributes, Extension,
    StarterKit, Underline, Highlight, Mention, Link, Image, TextStyle,
    Table, TableRow, TableHeader, TableCell, Subscript, Superscript,
    PluginKey,
};
