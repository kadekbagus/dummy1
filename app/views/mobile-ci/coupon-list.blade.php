@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    @if($data->status === 1)
        @if(sizeof($data->records) > 0)
            @foreach($data->records as $promo)
                <div class="main-theme catalogue promo-list" id="promo-{{$promo->promotion_id}}">
                    <div class="row row-xs-height catalogue-top">
                        <div class="col-xs-6 catalogue-img col-xs-height col-middle">
                            <div class="zoom-wrapper">
                                <div class="zoom"><a href="{{ asset($promo->promo_image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
                            </div>
                            <a href="{{ asset($promo->promo_image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($promo->promo_image) }}"></a>
                        </div>
                        <div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
                            <div class="row">
                                <div class="col-xs-12">
                                    <h3>{{ $promo->promotion_name }}</h3>
                                </div>
                                <div class="col-xs-12">
                                    <h4>{{ Lang::get('mobileci.coupon_detail.coupon_code_label') }}: {{ $promo->issued_coupon_code }}</h4>
                                </div>
                                <div class="col-xs-12">
                                    @if($promo->is_permanent == 'N')
                                        @if(strtotime($promo->expired_date) < strtotime($promo->end_date))
                                        <h4>{{ Lang::get('mobileci.coupon_detail.validity_label') }}: <br>{{ date('j M Y', strtotime($promo->expired_date)) }}</h4>
                                        @else
                                        <h4>{{ Lang::get('mobileci.coupon_detail.validity_label') }}: <br>{{ date('j M Y', strtotime($promo->end_date)) }}</h4>
                                        @endif
                                    @else
                                        <h4>{{ Lang::get('mobileci.coupon_detail.validity_label') }}: <br>{{ date('j M Y', strtotime($promo->expired_date)) }}</h4>
                                    @endif
                                </div>
                                <div class="col-xs-6 catalogue-control text-right pull-right">
                                    <div class="circlet btn-blue detail-btn pull-right vertically-spaced">
                                        <a href="{{ url('customer/coupon?couponid='.$promo->issued_coupon_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="row padded">
                <div class="col-xs-12">
                    <h4>{{ Lang::get('mobileci.coupon_list.no_coupon') }}</h4>
                </div>
            </div>
        @endif
    @endif
@stop

@section('modals')
  
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
<script type="text/javascript">
    // window.onunload = function(){};
    $(window).bind("pageshow", function(event) {
        if (event.originalEvent.persisted) {
            window.location.reload() 
        }
    });
    $(document).ready(function(){
        if(window.location.hash){
            var hash = window.location.hash;
            var producthash = "#promo-"+hash.replace(/^.*?(#|$)/,'');
            console.log(producthash);
            var hashoffset = $(producthash).offset();
            var hashoffsettop = hashoffset.top-68;
            setTimeout(function() {
                $(window).scrollTop(hashoffsettop);
            }, 1);
        }
    });
</script>
@stop
