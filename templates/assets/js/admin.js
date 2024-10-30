jQuery(document).ready(function($) {

    $("body").on("click","a.change-table",function(){
        var table = $(this).data('table');

        $('.change-table').removeClass('active');
        $(this).addClass('active');

        $('.select-table').hide();
        $('#'+table).show();
    });

    $("body").on("click","#import-excel-file", function(event) {
        event.preventDefault();
        $(this).attr("disabled", true);
        send_excel_file_import();
    });

    $('#uploadXLSX').on('change', function () {
        var formData = new FormData();
        for(var i = 0; i < this.files.length; i++) {
            formData.append('file', this.files[i]);
            formData.append('action', 'upload_curation_links');
        }
            $.ajax({
                url: admin.ajaxurl,
                type: 'POST',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (response) {
                    if(response.status === 'true') {
                        alert("Table was refreshed!");
                        curation_links.replaceData(response.table);
                    }
                }
            })

    });

    function send_excel_file_import()
    {
        var formData = new FormData();
        formData.append('action', 'import_variations');
        $('.select-import-column').each( function(){
            formData.append($(this).attr('name'), $(this).find('option:selected').attr("name"));
        });

        $.ajax({
            url: admin.ajaxurl,
            type: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success:function(response) {
                if(response.status === 'finished') {
                    $('#import-excel-file').attr("disabled", false);
                    $('.progress-info').hide();
                    alert('Import is Finished!');
                } else {
                    $('.progress-info').show();
                    $('.progress-info .text-progress span').html(response.offset+" / "+response.all_count);
                    $('#seo-scan-progress-bar').attr('max', response.all_count).attr('value', response.offset);
                    send_posts_types(response.page_import, response.import_id, response.status);
                }
            }
        });
    }

    function send_posts_types(page_import, import_id, status)
    {
        $.ajax({
            url: admin.ajaxurl,
            data: {
                'action': 'import_variations',
                'page_import': page_import,
                'import_id': import_id,
                'status': status
            },
            type:'POST',
            dataType: 'json',
            success:function(response) {
                if(response.status === 'finished') {
                    $('#import-excel-file').attr("disabled", false);
                    $('.progress-info').hide();
                    alert('Import is Finished!');
                } else {
                    $('.progress-info').show();
                    $('.progress-info .text-progress span').html(response.offset+" / "+response.all_count);
                    $('#seo-scan-progress-bar').attr('max', response.all_count).attr('value', response.offset);
                    send_posts_types(response.page_import, response.import_id, response.status);
                }
            }
        });
    }

    (function() {
        $(function() {
            $.tips({
                action: 'focus',
                element: '.error',
                tooltipClass: 'error'
            });
            $.tips({
                action: 'click',
                element: '.clicktips',
                preventDefault: false
            });
            return $.tips({
                action: 'hover',
                element: '.hover',
                preventDefault: false,
                html5: false
            });
        });
    }).call(this);
});