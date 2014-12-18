<!-- Search Product Modal -->
<div class="modal fade" id="SearchProducts" tabindex="-1" role="dialog" aria-labelledby="SearchProduct" aria-hidden="true">
  <div class="modal-dialog orbit-modal">
    <div class="modal-content">
      <div class="modal-header orbit-modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title" id="SearchProduct">Cari Produk</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
        	<label for="keyword">Cari berdasarkan : Nama Produk, Kode dan Deskripsi</label>
    		  <input type="text" class="form-control" id="keyword" placeholder="Input keywords">
        </div>
      </div>
      <div class="modal-footer">
        <form name="signUp" id="signUp" method="post" action="{{ url('/customer/signup') }}">
          <input type="hidden" name="emailSignUp" id="emailSignUp" value="">
          <button type="submit" class="btn btn-info" id="signup">Cari</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
	$('#searchBtn').click(function(){
		$('#SearchProducts').modal();
	});
  $('#backBtn').click(function(){
    window.history.back()
  });
</script>