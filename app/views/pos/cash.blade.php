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
           <div class="row" style="padding-left: 20px">
                <div class="col-md-12" >
                    <div class="col-md-3"><b><h4>TOTAL ITEMS<br><% cart.totalitem %></b></h4></div>
                    <div class="col-md-3"><b><h4>SUBTOTAL<br><% cart.subtotal %></b></h4></div>
                    <div class="col-md-3"><b><h4>VAT<br><% cart.vat %></b></h4></div>
                    <div class="col-md-3"><b><h4>TOTAL TO PAY<br><% cart.totalpay %></b></h4></div>
                </div>
           </div>
            <form>
              <div class="form-group">
                <label for="exampleInputEmail1">Total bayar</label>
                <input type="text" class="form-control text-right" id="exampleInputEmail1" placeholder="Total bayar">
              </div>
              <div class="form-group">
                <label for="exampleInputEmail1">Nominal Tunai</label>
                <input type="text" class="form-control text-right" id="exampleInputEmail1" placeholder="Nominal Tunai">
              </div>
              <div class="form-group">
                <label for="exampleInputEmail1">Kembalian</label>
                <input type="text" class="form-control text-right" id="exampleInputEmail1" placeholder="Kembalian">
              </div>

             <p class="text-center"><button type="submit" class="btn btn-lg btn-danger" style="padding-left: 89px; padding-right: 89px">Cancel</button></p>
             <p class="text-center"> <button type="submit" class="btn btn-lg btn-primary" style="background-color: #2c71a3; padding-left: 83px; padding-right: 83px">Continue</button></p>
            </form>
    </div>
</div>

@stop

