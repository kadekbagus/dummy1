@extends('pos.layouts.default')
@section('content')

<div class="ng-cloak" ng-controller="dashboardCtrl">

    <div class="container-fluid" style="border-bottom:1px solid #c0c0c0">
            <div class="header">
              <img src=" {{ URL::asset('templatepos/images/logo_matahari.png') }}"  class="img" style="width: 64px">
              <h1>MATAHARI DEPARTMENT STORE</h1>

                                               <div class="btn-group "   style="float: right; padding-top: 30px; padding-left: 10px;padding-right: 20px" dropdown>

                                                      <button type="button" class="btn btn-primary" style="background-color: #2c71a3;"><% datauser.username %> </button>
                                                      <button type="button" class="btn btn-primary dropdown-toggle"style="background-color: #2c71a3;" dropdown-toggle>
                                                        <span class="caret"></span>
                                                        <span class="sr-only">Split button!</span>
                                                      </button>
                                                      <ul class="dropdown-menu" role="menu">
                                                        <li><a href="#" data-ng-click="logoutfn()">Keluar</a></li>
                                                      </ul>
                                                </div>
             <p  style="float: right; padding-top: 40px;" >Tamu 0076 | <% datetime %></p>





            </div>

    </div>

    <div class="container" >

    <div class="row">
        <div class="col-md-12">
            <div class="col-md-8">
                <div class="orbit-component table-attribute-top">
                    <div class="row">
                         <div class="col-md-6" style="margin-top: 6px"><h3>KERANJANG BELANJA</h3></div>
                         <div class="col-md-6 text-right"> <button class="btn btn-primary" style="background-color: #2c71a3;" data-ng-click="loginFn()" type="submit">SCAN KERANJANG</button></div>
                    </div>

                </div>
                <div class="table-responsive">
                    <table class="table table-bordered">
                {{--<img src=" {{ URL::asset('templatepos/images/ss.jpg') }}"  class="img-thumbnail">--}}
                        <tr>
                            <th class="text-center">UPC</th>
                            <th class="text-center">NAMA</th>
                            <th class="text-center">JUMLAH</th>
                            <th class="text-center">HARGA TOTAL</th>
                        </tr>
                        <tr>
                            <td>1</td>
                            <td></td>
                            <td style="width: 200px">
                                 <div class="input-group ui-spinner" data-ui-spinner="">
                                      <span class="input-group-btn">
                                                                             <button type="button" class="btn btn-primary" data-spin="up">
                                                                                 <i class="fa fa-minus"></i>
                                                                             </button>
                                                                         </span>
                                      <input type="text" class="spinner-input form-control" style="margin-top: 5px !important;">
                                      <span class="input-group-btn">
                                                                             <button type="button" class="btn btn-primary" data-spin="down">
                                                                                 <i class="fa fa-plus"></i>
                                                                             </button>
                                                                         </span>&nbsp;
                                      <span class="input-group-btn">
                                                                              <button type="button" class="btn btn-danger" style="background-color: #D60000 ;" >
                                                                                      <i class="fa fa-trash"></i>
                                                                              </button>
                                                                          </span>
                                 </div>
                            </td>
                            <td class="text-right">1</td>
                        </tr>
                        <tr>
                            <td>1</td>
                            <td>1.000</td>
                            <td style="width: 200px">
                                 <div class="input-group ui-spinner" data-ui-spinner="">
                                      <span class="input-group-btn">
                                                                             <button type="button" class="btn btn-primary" data-spin="up">
                                                                                 <i class="fa fa-minus"></i>
                                                                             </button>
                                                                         </span>
                                      <input type="text" class="spinner-input form-control" style="margin-top: 5px !important;">
                                      <span class="input-group-btn">
                                                                             <button type="button" class="btn btn-primary" data-spin="down">
                                                                                 <i class="fa fa-plus"></i>
                                                                             </button>
                                                                         </span>&nbsp;
                                      <span class="input-group-btn">
                                                                              <button type="button" class="btn btn-danger" >
                                                                                      <i class="fa fa-trash"></i>
                                                                              </button>
                                                                          </span>
                                 </div>
                            </td>
                            <td class="text-right">3.000</td>
                        </tr>

                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table orbit-component table-noborder">
                        <tr>
                            <td colspan="3"><b>HARGA TOTAL</b></td>
                            <td class="text-right">1</td>
                        </tr>
                        <tr>
                            <td colspan="3"><b>HARGA TOTAL TANPA PAJAK</b></td>
                            <td class="text-right">1</td>
                        </tr>

                        <tr>
                            <td colspan="3"><b>HARGA BELANJA TOTAL</b></td>
                            <td class="text-right"> 1</td>
                        </tr>

                        <tr>
                            <td colspan="3"><b>HARGA TOTAL DENGAN PAJAK</b></td>
                            <td class="text-right">1</td>
                        </tr>
                        <tr>
                            <td> <button class="btn btn-primary" style="background-color: #2c71a3;" data-ng-click="loginFn()" type="submit">CART BARU</button> &nbsp; <button class="btn btn-primary" style="background-color: #2c71a3;" data-ng-click="loginFn()" type="submit">HAPUS CART</button></td>

                            <td> <button class="btn btn-primary" style="background-color: #2c71a3;" data-ng-click="loginFn()" type="submit">BAYAR</button></td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <div class="orbit-component table-attribute-top">

                      <div class="row">
                          <div class="col-md-12"><h4 class="text-center">KATALOG PRODUK</h4>
                            <div class="input-group">
                                     <div class="input-group-addon"><span class="glyphicon glyphicon-search" aria-hidden="true"></span></div>
                                     <input type="text" class="form-control"   id="exampleInputEmail2" placeholder="Cari Produk">
                            </div>
                          </div>

                      </div>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

@stop

