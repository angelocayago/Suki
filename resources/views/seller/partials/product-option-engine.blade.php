<script>
// Shared pure operations: regeneration preserves values by option/value identity.
globalThis.SukiProductOptions = (() => {
    const fold = value => String(value).trim().toLowerCase();
    const identity = options => JSON.stringify(Object.entries(options).map(([name,value]) => [fold(name),fold(value)]).sort((a,b) => a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : 0));
    function normalize(raw) {
        const groups = [], names = new Set();
        for (const row of raw) {
            const name = row.name.trim(), input = row.values.trim();
            if (!name && !input) continue;
            if (name.length > 40 || !/^[\p{L}\p{N}][\p{L}\p{N} _/().%\-]*$/u.test(name) || ['__proto__','prototype','constructor'].includes(fold(name))) throw Error('Enter meaningful, unique option names of at most 40 characters.');
            if (names.has(fold(name))) throw Error('Option names must be unique.');
            names.add(fold(name));
            const seen = new Set(), values = [];
            for (const entry of input.split(',')) {
                const value = entry.trim();
                if (!value) continue;
                if (value.length > 100 || /\p{C}/u.test(value) || !/[\p{L}\p{N}]/u.test(value)) throw Error('Enter meaningful option values of at most 100 characters.');
                if (!seen.has(fold(value))) { seen.add(fold(value)); values.push(value); }
            }
            if (!values.length || values.length > 20) throw Error('Each option needs 1–20 distinct values.');
            groups.push({name,values});
        }
        if (groups.length > 3) throw Error('Use at most 3 option groups.');
        return groups;
    }
    function combinations(groups) {
        let rows = [{}];
        for (const group of groups) {
            if (rows.length * group.values.length > 50) throw Error('Use at most 50 variant combinations.');
            rows = rows.flatMap(row => group.values.map(value => Object.fromEntries([...Object.entries(row),[group.name,value]])));
        }
        return groups.length ? rows : [];
    }
    function regenerate(groups, previous) {
        return combinations(groups).map(options => ({...(previous.get(identity(options)) || {}), options}));
    }
    return {identity,normalize,combinations,regenerate};
})();
</script>
