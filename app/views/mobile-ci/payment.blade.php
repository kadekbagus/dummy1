@extends('mobile-ci.layout')

@section('content')
<div class="container vertically-spaced">
  <div class="row">
    <div class="col-xs-12">
      <form role="form" name="paymentForm" method="POST" action="{{ url('/app/v1/customer/savetransaction') }}">
        <div class="form-group">
          <label for="exampleInputEmail1">Name</label>
          <input type="text" class="form-control" id="exampleInputEmail1" placeholder="Enter Name">
        </div>
        <div class="form-group">
          <label for="exampleInputEmail1">Card Type</label>
          <select class="form-control">
            <option value="1">Visa</option>
            <option value="2">Master Card</option>
        </select>
        </div>
        <div class="form-group">
          <label for="exampleInputEmail1">Card Number</label>
          <input type="text" class="form-control" id="exampleInputEmail1" placeholder="Enter Card Number">
        </div>
        <div class="form-group">
            <label for="exampleInputEmail1">Expire on</label>
            <div class="row">
            <div class="col-xs-6">
              <select class="form-control">
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                  <option value="4">4</option>
                  <option value="5">5</option>
                  <option value="6">6</option>
                  <option value="7">7</option>
                  <option value="8">8</option>
                  <option value="9">9</option>
                  <option value="10">10</option>
                  <option value="11">11</option>
                  <option value="12">12</option>
              </select>
            </div>
            <div class="col-xs-6">
              <select class="form-control">
                  <option value="2015">2015</option>
                  <option value="1999">2014</option>
                  <option value="1999">2013</option>
                  <option value="1999">2012</option>
                  <option value="1999">2011</option>
                  <option value="1999">2010</option>
              </select>
            </div>
          </div>
        </div>
        <div class="form-group">
            <label for="exampleInputEmail1">CCV</label>
            <div class="row">
              <div class="col-xs-6">
                <input type="text" class="form-control" id="exampleInputEmail1" placeholder="Enter CCV">
              </div>
            </div>
        </div>
        <div class="form-group pull-right">
            <button type="submit" class="btn btn-success btn-block">Submit</button>
        </div>
      </form>
    </div>
  </div>
  <div class="row">
    <div class="col-xs-12 text-center"> 
      <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" style="margin: 0 auto;" />
    </div>
  </div>
</div>
@stop
