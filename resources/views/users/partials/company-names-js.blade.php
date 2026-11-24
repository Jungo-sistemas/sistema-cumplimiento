{{--
    Nombres de empresa en los selectores de Usuarios. Algunas se llaman distinto en Procesos
    (VIDENCI → SAVSA, MIGAR CAPITAL → MIGAR…): se muestran las dos y se encuentran por cualquiera.
    Cada empresa trae name, process_name y show_in_processes.
--}}
<script>
    function companyFullLabel(c) {
        const procesos = c.process_name || c.name;
        return procesos !== c.name && c.show_in_processes ? c.name + ' · ' + procesos + ' en Procesos' : c.name;
    }

    function companyMatches(c, q) {
        return !q || [c.name, c.process_name].some(n => (n || '').toLowerCase().includes(q));
    }
</script>
