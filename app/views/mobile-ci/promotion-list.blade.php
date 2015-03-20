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
                                <div class="zoom"><a href="{{ asset($promo->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
                            </div>
                            <a href="{{ asset($promo->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($promo->image) }}"></a>
                        </div>
                        <div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
                            <div class="row">
                                <div class="col-xs-12">
                                    <h3>{{ $promo->promotion_name }}</h3>
                                </div>
                                <?php echo var_dump($promo); exit;?>
                                
                                <div class="col-xs-12">
                                    @if($promo->is_permanent == 'Y')
                                        <h4>{{ Lang::get('mobileci.catalogue.from')}}: {{ date('j M Y', strtotime($promo->begin_date)) }}</h4>
                                    @else
                                        <h4>{{ date('j M Y', strtotime($promo->begin_date)) }} - {{ date('j M Y', strtotime($promo->end_date)) }}</h4>
                                    @endif
                                </div>
                                <div class="col-xs-6 catalogue-control text-right pull-right">
                                    <div class="circlet btn-blue detail-btn pull-right vertically-spaced">
                                        <a href="{{ url('customer/promotion?promoid='.$promo->promotion_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
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
                    <h4>{{ Lang::get('mobileci.promotion_list.no_promo') }}</h4>
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