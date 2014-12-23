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
                                <td><a href=""  data-ng-click="showdetailFn()"><b> <% v.product_name %></b></a> <br><% v.upc %></td>
                                <td style="width: 200px">
                                    <div class="input-group ui-spinner" data-ui-spinner="">
                                          <span class="input-group-btn">
                                                                  <button type="button" class="btn btn-primary"  data-ng-click="qaFn(k,'m')" data-spin="up">
                                                                      <i class="fa fa-minus"></i>
                                                                  </button>
                                                              </span>
                                          <input type="text" class="spinner-input form-control"  data-ng-model="cart[k]['qty']" data-ng-change="qtychangemanualFn()" numbers-only="numbers-only" style="margin-top: 5px !important;">
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
                                <td class="text-right"><% v.hargatotal %></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <tr>
                            <td class="text-center"><b><h4>TOTAL ITEM<br><% totalitem %></b></h4></td>
                            <td class="text-center"><b><h4>SUBTOTAL<br><% subtotal %></b></h4></td>
                            <td class="text-center"><b><h4>VAT<br><% vat %></b></h4> </td>
                            <td class="text-center"><b><h4>TOTAL TO PAY<br><% totalpay %></b></h4></td>
                        </tr>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table  orbit-component table-noborder">
                        <tr>
                            <td colspan="3"> <button class="btn btn-danger"  data-toggle="modal" data-backdrop="static" data-target="#myModalNewCart" type="submit">CART BARU</button> &nbsp; <button class="btn btn-primary" style="background-color: #2c71a3;" data-toggle="modal" data-backdrop="static" data-target="#myModalDeleteCart"  type="submit">HAPUS CART</button></td>
                            <td class="text-right"> <button class="btn btn-success" style="background-color: #009933;" data-toggle="modal" data-backdrop="static" data-target="#myModalcheckout" data-ng-click="checkoutFn('b')" type="submit">BAYAR</button></td>
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
                <div class="orbit-component table-attribute-top" style="background-color: #B3B3B3;overflow: auto;height: 595px;overflow-x: hidden; padding-top: 1px" id="loading" >
                      <div class="row">
                      <div data-ng-if="productnotfound">
                           <p class="text-center"> Produk yang dicari tidak ditemukan </p>
                      </div>
                          <div class="col-md-6" data-ng-repeat="(k,v) in product" class="repeat-item">
                                <div ng-class="k % 2 == 0 ? 'mini-box <% v.disabled %>' : 'mini-boxright'"  data-toggle="modal" data-backdrop="static" data-target="#myModal" data-ng-click="showdetailFn(k)">
                                     <table>
                                           <tr>
                                                <td rowspan="4"> <img src=" {{ URL::asset('templatepos/images/ss.jpg') }}"  class="img64_64"></td>
                                           </tr>
                                           <tr>
                                                <td><h5>&nbsp;<b><% v.product_name.substr(0,9) %></b><br>&nbsp;<b style="font-size: 10px"><% v.upc_code %></b></h5></td>
                                           </tr>
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

    <!-- Modal Product Detail-->
    <div class="modal fade" id="myModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
            <h4 class="modal-title" id="myModalLabel">Cart Baru</h4>
          </div>
          <div class="modal-body">
            <% productmodal.product_name %>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" data-dismiss="modal" data-ng-click="inserttocartFn()">Tambahkan ke keranjang belanja</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Cart Baru-->
    <div class="modal fade" id="myModalNewCart" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
            <h4 class="modal-title" id="myModalLabel"><b>Cart Baru</b></h4>
          </div>
          <div class="modal-body">
                  <p><b>Anda yakin akan ingin membuat cart baru ?</b></p>
                  <p>Setelah anda memilih "Ya", maka cart baru akan </p>
                  <p>menggantikan cart sebelumnya</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color:#56BDF1;color:#2C71A3" data-ng-click="newcartFn()">Ya</button>
            <button type="button" class="btn btn-danger" data-dismiss="modal" >Tidak</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Hapus Cart-->
    <div class="modal fade" id="myModalDeleteCart" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
            <h4 class="modal-title" id="myModalLabel"><b>Hapus Cart</b></h4>
          </div>
          <div class="modal-body">
                  <p><b>Anda yakin akan ingin mengahpus cart ?</b></p>
                  <p>Setelah anda memilih "Ya", maka semua </p>
                  <p>produk dalam cart ini akan dihapus</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color:#56BDF1;color:#2C71A3" data-ng-click="deletecartFn()">Ya</button>
            <button type="button" class="btn btn-danger" data-dismiss="modal">Tidak</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Checkout-->
    <div class="modal fade" id="myModalcheckout" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
            <h4 class="modal-title text-center" id="myModalLabel"><b>PILIH CARA PEMBAYARAN</b></h4>
          </div>
          <div class="modal-body text-center">
                  <p><button type="button" class="btn btn-success btn-lg" data-dismiss="modal" style="background-color: #009933; padding-left: 83px; padding-right: 83px " data-ng-click="checkoutFn('t')">TUNAI</button></p>
                  <p><button type="button" class="btn btn-success btn-lg" data-dismiss="modal" style="background-color: #009933; " data-ng-click="checkoutFn('k')">KARTU DEBIT/KREDIT</button> </p>

          </div>
        </div>
      </div>
    </div>

</div>
</div>

@stop

