@extends('mobile-ci.layout-headless')

@section('ext_style')
  <style>
    .modal-backdrop{
      z-index:0;
    }
    #signup{
      display: none;
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
      <form name="loginForm" id="loginForm" action="{{ url('customer/login') }}" method="post">
        <div class="form-group">
          <input type="text" class="form-control" name="email" id="email" placeholder="Harap masukan alamat email Anda" />
        </div>
        <div class="form-group">
          <button type="submit" class="btn btn-info btn-block">Masuk</button>
        </div>
      </form>
    </div>
  </div>
@stop

@section('footer')
  <footer>
    <div class="row">
      <div class="col-xs-12 text-center">
        <p >Orbit - powered by DominoPos</p>
      </div>
    </div>
  </footer>
@stop

@section('modals')
  <!-- Modal -->
  <div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Tutup</span></button>
          <h4 class="modal-title" id="myModalLabel">Error</h4>
        </div>
        <div class="modal-body">
          <p id="errorModalText"></p>
        </div>
        <div class="modal-footer">
          <form name="signUp" id="signUp" method="post" action="{{ url('/customer/signup') }}">
            <input type="hidden" name="emailSignUp" id="emailSignUp" value="">
            <button type="submit" class="btn btn-success" id="signup">Daftar</button>
            <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
          </form>
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
        
        $('#signup').css('display','none');
        $('#errorModalText').text('');
        $('#emailSignUp').val('');

        if(!$('#email').val()) {
          $('#errorModalText').text('Harap isi email terlebih dahulu.');
          $('#errorModal').modal();
        }else{
          if(isValidEmailAddress($('#email').val())){
            $.ajax({
              method:'POST',
              url:apiPath+'customer/login',
              data:{
                email: $('#email').val()
              }
            }).done(function(data){
              if(data.status==='error'){
                $('#errorModalText').html('Email belum terdaftar.<br> Silahkan mendaftar sekarang.');
                $('#emailSignUp').val($('#email').val());
                $('#signup').css('display','inline-block');
                $('#errorModal').modal();  
              }
              if(data.data){
                // console.log(data.data);
                window.location.replace(homePath);
              }
            }).fail(function(data){
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