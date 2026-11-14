<script>
    (function () {
        const origin = document.getElementById('proposition-origin');
        if (!origin) return;

        const councilor = document.getElementById('author-councilor');
        const external = document.getElementById('author-external');
        const councilorBox = councilor ? councilor.closest('div') : null;
        const externalBox = external ? external.closest('div') : null;

        const toggle = () => {
            const isLegislative = origin.value === 'legislativo';
            if (councilorBox) councilorBox.classList.toggle('hidden', !isLegislative);
            if (externalBox) externalBox.classList.toggle('hidden', isLegislative);
            if (councilor) councilor.required = isLegislative;
            if (external) external.required = !isLegislative;
        };

        origin.addEventListener('change', toggle);
        toggle();
    })();
</script>
