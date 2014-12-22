@extends('pos.layouts.default')
@section('content')

<div class="ng-cloak" ng-controller="dashboardCtrl">

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
            <div class="col-md-8">
                <div class="orbit-component table-attribute-top">
                    <div class="row">
                         <div class="col-md-6" style="margin-top: 6px"><h3>KERANJANG BELANJA</h3></div>
                         <div class="col-md-6 text-right"> <button class="btn btn-primary" style="background-color: #2c71a3;" data-ng-click="loginFn()" type="submit">SCAN KERANJANG</button></div>
                    </div>

                </div>
                <div class="table-responsive"  style="overflow: auto;height: 465px" >
                    <table class="table">
                        <tr>
                            <th class="text-center">NAMA + UPC</th>
                            <th class="text-center">JUMLAH</th>
                            <th class="text-center">HARGA TOTAL</th>
                        </tr>
                        <tbody>
                           <tr data-ng-repeat="(k,v) in cart">
                                <td><a href=""  data-ng-click="showdetailFn()"><b> <% v.name %></b></a> <br><% v.upc %></td>
                                <td style="width: 200px">
                                    <div class="input-group ui-spinner" data-ui-spinner="">
                                          <span class="input-group-btn">
                                                                  <button type="button" class="btn btn-primary"  data-ng-click="qaFn(k,'m')" data-spin="up">
                                                                      <i class="fa fa-minus"></i>
                                                                  </button>
                                                              </span>
                                          <input type="text" class="spinner-input form-control"  data-ng-model="cart[k]['quantity']" numbers-only="numbers-only" style="margin-top: 5px !important;">
                                          <span class="input-group-btn">
                                              <button type="button" class="btn btn-primary" data-spin="down" data-ng-click="qaFn(k,'p')">
                                                  <i class="fa fa-plus"></i>
                                              </button>
                                          </span>&nbsp;&nbsp;
                                          <span class="input-group-btn">
                                                <button type="button" class="btn btn-danger" data-ng-click="qaFn(k,'d')" >
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                          </span>
                                    </div>
                                </td>
                                <td class="text-right">1</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <tr>
                            <td class="text-center"><b><h4>TOTAL ITEM</h4></b><br> 7</td>
                            <td class="text-center"><b><h4>SUBTOTAL</h4></b><br> 7</td>
                            <td class="text-center"><b><h4>VAT</h4></b><br> 7</td>
                            <td class="text-center"><b><h4>TOTAL TO PAY</h4></b><br> 7</td>
                        </tr>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table  orbit-component table-noborder">
                        <tr>
                            <td colspan="3"> <button class="btn btn-danger" data-ng-click="loginFn()" type="submit">CART BARU</button> &nbsp; <button class="btn btn-primary" style="background-color: #2c71a3;" data-ng-click="loginFn()" type="submit">HAPUS CART</button></td>
                            <td class="text-right"> <button class="btn btn-primary" style="background-color: #009933;" data-ng-click="loginFn()" type="submit">BAYAR</button></td>
                       </tr>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <div class="orbit-component table-attribute-top" >
                      <div class="row">
                          <div class="col-md-12"><h4 class="text-center">KATALOG PRODUK</h4><br>
                            <div class="input-group" id="loadingsearch">
                                     <div class="input-group-addon"><span class="glyphicon glyphicon-search" aria-hidden="true"></span></div>
                                     <input type="text" class="form-control"  data-ng-model="searchproduct" id="exampleInputEmail2" placeholder="Cari Produk">
                            </div>
                          </div>
                      </div>
                </div>
                <div class="orbit-component table-attribute-top" style="background-color: #B3B3B3;overflow: auto;height: 610px;overflow-x: hidden;" id="loading" >
                      <div class="row">
                      <div data-ng-if="productnotfound">
                           <p class="text-center"> Produk yang dicari tidak ditemukan </p>
                      </div>
                          <div class="col-md-6" data-ng-repeat="(k,v) in product" class="repeat-item">
                                <div ng-class="k % 2 == 0 ? 'mini-box' : 'mini-boxright'" data-ng-click="showdetailFn(k)">
                                     <table>
                                           <tr>
                                                <td rowspan="4"> <img src=" {{ URL::asset('templatepos/images/ss.jpg') }}"  class="img64_64"></td>
                                           </tr>
                                           <tr>
                                                <td><h5>&nbsp;<b><% v.product_name.substr(0,9) %></b></h5></td>
                                           </tr>
                                         {{--  <tr>
                                                <td><h6>99992827</h6></td>
                                           </tr>--}}
                                           <tr>
                                                <td class="text-right" style="width: 80px;"><h6><% v.price %></h6></td>
                                           </tr>
                                     </table>
                                </div>
                          </div>
                      </div>
                </div>

            </div>
        </div>
    </div>
             <script type="text/ng-template" id="productdetail.html">
                 <div class="modal-header">
                     <h3 class="modal-title">I'm a modal!</h3>
                 </div>
                 <div class="modal-body">
                      <% productmodal.product_name %>

                 </div>
                 <div class="modal-footer">
                     <button class="btn btn-primary" ng-click="ok()">OK</button>
                     <button class="btn btn-warning" ng-click="cancel()">Cancel</button>
                 </div>
             </script>
</div>
</div>

@stop

