@extends('mobile-ci.layout-headless')

@section('ext_style')
  <style>
    .modal-backdrop{
      z-index:0;
    }
  </style>
@stop

@section('content')
  <div class="row top-space">
    <div class="col-xs-12">
      <header>
        <div class="row vertically-spaced">
          <div class="col-xs-12 text-center">
            <span>Welcome!</span>
          </div>
        </div>
        <div class="row vertically-spaced">
          <div class="col-xs-12 text-center">
            <img src="{{ asset('mobile-ci/images/logo-default.png') }}" />
          </div>
        </div>
      </header>
      <form name="loginForm" id="loginForm">
        <div class="form-group">
          <input type="text" class="form-control" name="email" id="email" />
        </div>
        <div class="form-group">
          <button type="submit" class="btn btn-info btn-block">Login</button>
        </div>
      </form>
    </div>
  </div>
@stop

@section('modals')
  <!-- Modal -->
  <div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
          <h4 class="modal-title" id="myModalLabel">Error</h4>
        </div>
        <div class="modal-body">
          <p id="errorModalText"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@stop

@section('ext_script_bot')
  <script type="text/javascript">
    $(document).ready(function(){
      $('#loginForm').submit(function(event){
        $('#errorModalText').text('');
        if(!$('#email').val()) {
          $('#errorModalText').text('Harap isi email terlebih dahulu');
          $('#errorModal').modal();
        }else{
          $.ajax({
            method:'POST',
            url:apiPath+'customer/login',
            data:{
              email: $('#email').val()
            }
          }).done(function(data){

          }).fail(function(data){
            $('#errorModalText').text('Terjadi kesalahan koneksi. Mohon coba lagi.');
            $('#errorModal').modal();
          });
        }
        event.preventDefault();
      });
    });
  </script>
@stop