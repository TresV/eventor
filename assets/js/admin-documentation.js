(function () {
    function initDocumentationSearch() {
        var root = document.querySelector('[data-evt-docs-root]');
        if (!root) {
            return;
        }

        var searchInput = root.querySelector('[data-evt-docs-search]');
        var docItems = root.querySelectorAll('[data-evt-doc-item]');

        if (!searchInput || !docItems.length) {
            return;
        }

        searchInput.addEventListener('input', function () {
            var query = searchInput.value.trim().toLowerCase();

            docItems.forEach(function (item) {
                var haystack = (
                    item.getAttribute('data-doc-title') +
                    ' ' +
                    item.getAttribute('data-doc-description')
                ).toLowerCase();

                item.hidden = query !== '' && haystack.indexOf(query) === -1;
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDocumentationSearch);
    } else {
        initDocumentationSearch();
    }
}());
