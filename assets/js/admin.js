document.addEventListener('DOMContentLoaded', function() {
    const output = document.getElementById('wpso-tools-output');

    function bindButton(id, action, successMsg, errorMsg) {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.addEventListener('click', function() {
            output.textContent = action.loading || 'Processing...';
            jQuery.post(
                wpsoAdmin.ajaxUrl,
                {
                    action: action.name,
                    nonce: wpsoAdmin.nonce
                }
            ).done(function(response) {
                if (response.success) {
                    output.textContent = response.data.message || successMsg;
                } else {
                    output.textContent = response.data.message || errorMsg;
                }
            }).fail(function() {
                output.textContent = errorMsg;
            });
        });
    }

    bindButton('wpso-clear-cache', { name: 'wpso_clear_cache', loading: 'Clearing cache...' }, 'Cache cleared successfully!', 'Failed to clear cache.');
    bindButton('wpso-generate-css', { name: 'wpso_generate_critical_css', loading: 'Generating Critical CSS...' }, 'Critical CSS generated!', 'Failed to generate Critical CSS.');
    bindButton('wpso-optimize-js', { name: 'wpso_optimize_js', loading: 'Optimizing JavaScript...' }, 'JavaScript optimized!', 'Failed to optimize JavaScript.');
    bindButton('wpso-optimize-images', { name: 'wpso_optimize_images', loading: 'Optimizing images...' }, 'Images optimized!', 'Failed to optimize images.');
    bindButton('wpso-optimize-db', { name: 'wpso_optimize_database', loading: 'Optimizing database...' }, 'Database optimized!', 'Failed to optimize database.');
    bindButton('wpso-test-performance', { name: 'wpso_test_performance', loading: 'Testing performance...' }, 'Performance test complete!', 'Failed to test performance.');
});