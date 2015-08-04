@extends('pos.layouts.default')
@section('content')

<style type="text/css">
    body{
        background: url('<% pathPublic + 'images/mall_bg.jpg' %>') no-repeat #ffffff;
        background-size: cover;
    }
</style>
<div class="page-err" ng-controller="Error404Ctrl">
    <div class="err-container">
        <div class="text-center">
            <div class="err-status">
                 <h1>403</h1>
            </div>
            <div class="err-message">
                <h2>Access Forbidden</h2>
            </div>
            <div class="err-body">

            </div>
        </div>
    </div>
</div>
<div class="footer-err text-center">
    <section class="bottom-section text-center">
        <img ng-src="<% pathPublic + 'images/orbit_footer.png' %>">
    </section>
</div>
@stop