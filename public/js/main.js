$(function() {
    var token = $('meta[name="csrf-token"]').attr('content');
    if (token) {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
    }

    $(document).ajaxError(function(event, xhr) {
        if (xhr.status === 401) {
            if (confirm('Please log in first.')) {
                window.location.href = '/login';
            }
        } else if (xhr.status === 403) {
            alert('You do not have permission to perform this action.');
        }
    });

    $('#searchInput').on('keypress', function(e) {
        if (e.which === 13) {
            $(this).closest('form').submit();
        }
    });
});
