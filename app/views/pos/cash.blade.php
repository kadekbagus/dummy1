@extends('pos.layouts.default')
@section('content')

<div class="ng-cloak" ng-controller="cashCtrl">

    <div class="container-fluid" style="border-bottom:1px solid #c0c0c0">
            <div class="header">
                <img src=" {{ URL::asset('templatepos/images/logo_matahari.png') }}"  class="img" style="width: 64px">
                <h1>MATAHARI DEPARTMENT STORE</h1>
                <div class="btn-group "   style="float: right; padding-top: 40px; padding-left: 10px;padding-right: 20px" dropdown>

                     <% datauser.username %>&nbsp;<span class="down"  dropdown-toggle><i class="fa fa-caret-down"></i></span>
                      {{--<button type="button" class="btn btn-primary dropdown-toggle"style="background-color: #2c71a3;" dropdown-toggle>
                        <span class="caret"></span>
                        <span class="sr-only">Split button!</span>
                      </button>--}}
                       {{-- <i class="fa fa-sign-out"></i>--}}
                      <ul class="dropdown-menu" style="min-width: 60px" role="menu">
                        <li> <a href="#" data-ng-click="logoutfn()">Keluar</a></li>
                      </ul>
                </div>
                <p  style="float: right; padding-top: 40px;" >Guest  <% guests %> | <% datetime %></p>
            </div>
    </div>

    <div class="container">
    <div class="row">
        <div class="col-md-12">
           Cash
        </div>
    </div>
</div>
</div>

@stop

