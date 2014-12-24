@extends('mobile-ci.layout-headless')

@section('ext_style')
  <style>
    .modal-backdrop{
      z-index:0;
    }
    .img-responsive{
      margin:0 auto;
    }
  </style>
@stop

@section('content')
  <div class="row top-space">
    <div class="col-xs-12">
      <header>
        <div class="row vertically-spaced">
          <div class="col-xs-12 text-center">
            <span class="greetings">{{ Lang::get('mobileci.greetings.welcome') }}</span>
          </div>
        </div>
        <div class="row vertically-spaced">
          <div class="col-xs-12 text-center">
            <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" />
          </div>
        </div>
      </header>
      <form name="loginForm" id="loginForm">
        <div class="form-group">
          <input type="text" class="form-control" name="email" id="email" value="{{ $email }}"/>
        </div>
        <div class="form-group">
          <button type="submit" class="btn btn-success btn-block">Daftar</button>
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
      function isValidEmailAddress(emailAddress) {
        var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
        return pattern.test(emailAddress);
      };
      $('#loginForm').submit(function(event){
        $('#errorModalText').text('');
        if(!$('#email').val()) {
          $('#errorModalText').text('Harap isi email terlebih dahulu.');
          $('#errorModal').modal();
        }else{
          if(isValidEmailAddress($('#email').val())){
            $.ajax({
              method:'POST',
              url:apiPath+'customer/signup',
              data:{
                email: $('#email').val()
              }
            }).done(function(data){
              if(data.status==='error'){
                $('#errorModalText').html(data.message);
                $('#errorModal').modal();  
              }
              if(data.data){
                // console.log(data.data);
                window.location.replace(homePath);
              }
            }).fail(function(data){
              console.log(data.responseJSON);
              $('#errorModalText').text(data.responseJSON.message);
              $('#errorModal').modal();
            });
          } else {
            $('#errorModalText').text('Email tidak valid.');
            $('#errorModal').modal();
          }
        }
        event.preventDefault();
      });
    });
  </script>
@stop