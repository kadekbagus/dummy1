<!doctype html>
<html>
  <head>
    @include('mobile-ci.head')
  </head>
  <body>
    @include('mobile-ci.toolbar')
    <div class="headed-layout content-container">
      @yield('content')
    </div>
  </body>
</html>
