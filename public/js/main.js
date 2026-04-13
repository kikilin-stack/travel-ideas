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
            if (confirm('请先登录')) {
                window.location.href = '/login';
            }
        } else if (xhr.status === 403) {
            alert('您没有权限执行此操作');
        }
    });

    $('#searchInput').on('keypress', function(e) {
        if (e.which === 13) {
            $(this).closest('form').submit();
        }
    });
});
