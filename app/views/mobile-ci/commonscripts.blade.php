<!-- Search Product Modal -->
<div class="modal fade" id="SearchProducts" tabindex="-1" role="dialog" aria-labelledby="SearchProduct" aria-hidden="true">
  <div class="modal-dialog orbit-modal">
    <div class="modal-content">
      <div class="modal-header orbit-modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title" id="SearchProduct">Cari Produk</h4>
      </div>
      <div class="modal-body">
        <form method="GET" name="searchForm" id="searchForm" action="{{ url('/customer/search') }}">
          <div class="form-group">
          	<label for="keyword">Cari berdasarkan : Nama Produk, Kode dan Deskripsi</label>
      		  <input type="text" class="form-control" name="keyword" id="keyword" placeholder="Input keywords">
          </div>
        </form>
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-info" id="searchProductBtn">Cari</button>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
	$('#searchBtn').click(function(){
		$('#SearchProducts').modal();
    setTimeout(function(){
      $('#keyword').focus();
    }, 500);
	});
  $('#searchProductBtn').click(function(){
    $('#searchForm').submit();
  });
  $('#backBtn').click(function(){
    window.history.back()
  });
  $('#search-tool-btn').click(function(){
    $('#search-tool').toggle();
  });
</script>