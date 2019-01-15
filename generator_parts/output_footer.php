  <!--  Footer -->
  <footer class="footer">
    <div class="container">
      <span class="text-muted">
        <b>replaceDBName</b> is using
        <a target="_blank" href="http://php.net/">PHP</a>,&nbsp;
        <a target="_blank" href="http://getbootstrap.com/">Bootstrap4</a>,&nbsp;
        <a target="_blank" href="https://jquery.com/">jQuery</a>,&nbsp;
        <a target="_blank" href="http://visjs.org/">visjs</a>
      </span>
    </div>
  </footer>
  <!-- JS -->
  <script src="js/main.js"></script>
  <script>
    $(document).ready(function() {
      DB.API_URL = 'api.php';
      // Create objects
      ###JS_TABLE_OBJECTS###
      // Loading disable
      $('.initloadingtext').hide();
      $('.mainapp').show();
    });
  </script>
  <script src="js/custom.js"></script>
</body>
</html>
