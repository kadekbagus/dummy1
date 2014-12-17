@extends('pos.layouts.default')
@section('content')
<div class="main-container">
    <div class="row">
        <div class="col-md-12">
            <div class="col-md-8"><h3><b>The Grand Duck King</b></h3></div>
            <div class="col-md-4">
                <div class="row">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-6">
                                  <button class="btn btn-success btn-lg  text-center" type="submit">SCAN PRODUCT</button>
                            </div>
                            <div class="col-md-6">
                                   <button  class="btn btn-primary btn-lg text-center" type="submit">SCAN CART</button>
                            </div>
                        </div>

                        guest    2014
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="col-md-8">
                <div class="orbit-component table-attribute-top">
                    <h3>KERANJANG BELANJA</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">

                        <tr>
                            <td><img src=" {{ URL::asset('templatepos/images/ss.jpg') }}"  class="img-thumbnail"></td>
                            <td>Baked Lobster</td>
                            <td>Baked Lobster</td>
                            <td>Baked Lobster</td>
                        </tr>
                        <tr>
                            <td><img src=" {{ URL::asset('templatepos/images/ss.jpg') }}"  class="img-thumbnail"></td>
                            <td>1</td>
                            <td>1</td>
                            <td>1</td>
                        </tr>
                        <tr>
                            <td><img src=" {{ URL::asset('templatepos/images/ss.jpg') }}"  class="img-thumbnail"></td>
                            <td>1.000</td>
                            <td>2.000</td>
                            <td>3.000</td>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <div class="orbit-component table-attribute-top">
                    <h3>PRODUCT CATALOG</h3>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

