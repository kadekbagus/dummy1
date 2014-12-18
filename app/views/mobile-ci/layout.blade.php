<!doctype html>
<html>
  <head>
    @include('mobile-ci.head')
    @yield('ext_style')
  </head>
  <body>
    @include('mobile-ci.toolbar')
    <div class="headed-layout content-container">
      @yield('content')
    </div>
    @yield('modals')
    @yield('ext_script_bot')
  </body>
</html>
