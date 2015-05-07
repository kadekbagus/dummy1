<!-- Search Product Modal -->
<div class="modal fade" id="SearchProducts" tabindex="-1" role="dialog" aria-labelledby="SearchProduct" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="SearchProduct">{{ Lang::get('mobileci.modals.search_title') }}</h4>
            </div>
            <div class="modal-body">
                <form method="GET" name="searchForm" id="searchForm" action="{{ url('/customer/search') }}">
                    <div class="form-group">
                        <label for="keyword">{{ Lang::get('mobileci.modals.search_label') }}</label>
                        <input type="text" class="form-control" name="keyword" id="keyword" placeholder="{{ Lang::get('mobileci.modals.search_placeholder') }}">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" id="searchProductBtn">{{ Lang::get('mobileci.modals.search_button') }}</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="UPCerror" tabindex="-1" role="dialog" aria-labelledby="UPCerror" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title">{{ Lang::get('mobileci.modals.error_title') }}</h4>
            </div>
            <div class="modal-body">
                <p>
                    {{ Lang::get('mobileci.modals.message_error_upc') }}
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="spinner" tabindex="-1" role="dialog" aria-labelledby="spinner" aria-hidden="true" data-backdrop="static">
    <div class="spinners">
        <i class="fa fa-circle-o-notch fa-spin"></i>
    </div>
</div>

{{ HTML::script('mobile-ci/scripts/offline.js') }}
{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
<script type="text/javascript">
    $(document).ready(function(){
        $('#barcodeBtn2').click(function(){
            $('#get_camera2').click();
        });
        $('#get_camera2').change(function(){
            $('#spinner').modal();
            var formElement = document.getElementById("get_camera2");
            var data = new FormData();
            data.append('images[]', formElement.files[0]);
            console.log(data);
            $.ajax({
                url: apiPath+'customer/scan?orbit_session='+$.cookie('code_session'),
                method: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false
            }).done(function(data){
                console.log(data.data);
                if(data.data) {
                    window.location.assign(publicPath+'/customer/productscan?upc='+data.data);
                } else {
                    $('#UPCerror').modal();
                }
            }).fail(function(){
                $('#UPCerror').modal();
            }).always(function(){
                $('#spinner').modal('hide');
            });
        });

        $('#searchBtn').click(function(){
            $('#SearchProducts').modal();
            setTimeout(function(){
                $('#keyword').focus();
            }, 500);
        });
        $('#searchProductBtn').click(function(){
            $('#SearchProducts').modal('toggle');
            $('#searchForm').submit();
        });
        $('#backBtn').click(function(){
            window.history.back()
        });
        $('#search-tool-btn').click(function(){
            $('#search-tool').toggle();
        });
        if($('#cart-number').attr('data-cart-number') == '0'){
            $('.cart-qty').css('display', 'none');
        }
    });
</script>
