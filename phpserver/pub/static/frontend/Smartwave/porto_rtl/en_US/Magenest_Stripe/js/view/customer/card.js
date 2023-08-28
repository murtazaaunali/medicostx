/**
 * Created by thaivh on 23/3/17.
 */
require(
    [
        'jquery',
        'Magento_Ui/js/modal/confirm'
    ],
    function ($, confirmation) {
        $('button[id^="del-"]').click(function () {
            var id = $(this).val();
            confirmation({
                title: '',
                content: '<h2>Delete your selected card ?</h2>',
                actions: {
                    confirm: function () {
                        $.ajax({
                            showLoader: true,
                            type: 'POST',
                            url: window.delUrl,
                            dataType: "json",
                            data: {
                                id: id
                            },
                            success: function (response) {
                                if (response.success) {
                                    $('tr[id^="row-' + id + '"]').hide(400, function () {
                                        alert("Your card has been deleted!");
                                    });
                                    if (typeof(response.default) !== 'undefined' && response.default.length>0){
                                        idDefault = response.default;
                                        $('td[id^="status-' + idDefault + '"]').removeClass();
                                        $('td[id^="status-' + idDefault + '"]').addClass('default');
                                        $('td[id^="status-' + idDefault + '"]').html('DEFAULT');
                                    }
                                }else {
                                    alert("Something went wrong! Please try again!");
                                    $('td[id^="status-' + id + '"]').removeClass();
                                    $('td[id^="status-' + id + '"]').addClass('error');
                                    $('td[id^="status-' + id + '"]').html('ERROR');
                                }
                            },
                            error: function (response) {
                                alert("Has something wrong while deleting your card !");
                            }
                        })
                    },
                    cancel: function () {
                    },
                    always: function () {
                    }
                }
            })
        });
    });
