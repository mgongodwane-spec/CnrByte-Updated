<?php
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/dbh_conn.inc.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/config_session.inc.php';
    unset($_SESSION['current_dashboard']);
    $_SESSION['current_dashboard'] = 'admin_dashboard';

    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/admin/admin.shw.php';

    // Only allow admins
    if (($_SESSION['user_info']['rank'] ?? $_SESSION['user_rank'] ?? '') !== 'admin') {
        header("Location: /cnrbyte/signup_login/signup_login.pge.php");
        exit;
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Category Management</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; display: flex; gap: 40px; }
        .left, .right { flex: 1; }
        .form-section { margin-bottom: 30px; padding: 15px; border: 1px solid #ccc; border-radius: 6px; }
        label { display: block; margin-top: 10px; }
        input, select { padding: 6px; margin-top: 5px; width: 100%; max-width: 400px; }
        button { margin-top: 10px; padding: 8px 14px; cursor: pointer; }
        .tree { font-size: 14px; line-height: 1.5; }
        .tree ul { list-style: none; margin-left: 20px; padding-left: 15px; border-left: 1px dashed #aaa; }
        .tree li { margin: 4px 0; position: relative; }
        .highlight { font-weight: bold; color: #2a5d9f; }
        .toggle { cursor: pointer; margin-right: 6px; font-size: 0.9em; color: #2a5d9f; }
        .collapsed > ul { display: none; }
        .message { margin-top: 10px; font-size: 0.9em; color: green; }
        .category-product-count { display: inline-block; margin-left: 6px; padding: 2px 7px; border-radius: 12px; background: #eef2f7; color: #475569; font-size: 11px; white-space: nowrap; }
        @media (max-width: 800px) { body { display: block; padding: 12px; } .left, .right { width: 100%; } }
    </style>
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>


    <div class="left">
      <!-- Great Category -->
      <div class="form-section">
        <h2>Add Great Category</h2>
        <form id="formGreat">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <label>Category Name:</label>
          <input type="text" name="categories" required>
          <input type="hidden" name="level" value="great">
          <button type="submit">Add Great</button>
        </form>
        <div id="msgGreat" class="message"></div>
      </div>

      <!-- Grand Category -->
      <div class="form-section">
        <h2>Add Grand Categories</h2>
        <form id="formGrand">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <label>Select Great:</label>
          <select name="great_id" id="greatSelect" required>
            <option value="">-- Select Great --</option>
          </select>
          <label>Grand Categories (comma separated):</label>
          <input type="text" name="categories" placeholder="Drinks, Dairy, Bakery" required>
          <input type="hidden" name="level" value="grand">
          <button type="submit">Add Grands</button>
        </form>
        <div id="msgGrand" class="message"></div>
      </div>

      <!-- Parent Category -->
      <div class="form-section">
        <h2>Add Parent Categories</h2>
        <form id="formParent">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <label>Select Grand:</label>
          <select name="grand_id" id="grandSelect" required>
            <option value="">-- Select Grand --</option>
          </select>
          <input type="hidden" name="great_id" id="parentGreatId">
          <label>Parent Categories (comma separated):</label>
          <input type="text" name="categories" placeholder="Water, Juice, Soft Drinks" required>
          <input type="hidden" name="level" value="parent">
          <button type="submit">Add Parents</button>
        </form>
        <div id="msgParent" class="message"></div>
      </div>

      <!-- Child Category -->
      <div class="form-section">
        <h2>Add Child Categories</h2>
        <form id="formChild">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <label>Select Parent:</label>
          <select name="parent_id" id="parentSelect" required>
            <option value="">-- Select Parent --</option>
          </select>
          <input type="hidden" name="grand_id" id="childGrandId">
          <input type="hidden" name="great_id" id="childGreatId">
          <label>Child Categories (comma separated):</label>
          <input type="text" name="categories" placeholder="Still, Sparkling, Flavoured" required>
          <input type="hidden" name="level" value="child">
          <button type="submit">Add Children</button>
        </form>
        <div id="msgChild" class="message"></div>
      </div>
    </div>

    <div class="right">
      <h2>📂 Category Tree</h2>
      <div id="categoryTree" class="tree">Loading...</div>
    </div>

    <script>
        // Generic AJAX submit
        function ajaxSubmit(formId, msgId) {
          document.getElementById(formId).addEventListener('submit', function(e){
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);

            fetch('insert_category.php', { method:'POST', body:data })
              .then(res => res.text())
              .then(resp => {
                document.getElementById(msgId).textContent = resp;
                form.reset();
                // refresh everything gently
                refreshGreats();      // reload great select options
                loadTree();          // reload tree html
                // clear dependent selects
                document.getElementById('grandSelect').innerHTML = '<option value="">-- Select Grand --</option>';
                document.getElementById('parentSelect').innerHTML = '<option value="">-- Select Parent --</option>';
              });
          });
        }

        // Load category tree
        function loadTree() {
          fetch('get_category_tree.php')
            .then(res => res.text())
            .then(html => document.getElementById('categoryTree').innerHTML = html);
        }

        // Load great options (fresh list from server)
        function refreshGreats() {
          fetch('get_great.php')
            .then(res => res.json())
            .then(data => {
              const sel = document.getElementById('greatSelect');
              sel.innerHTML = '<option value="">-- Select Great --</option>';
              data.forEach(g => sel.innerHTML += `<option value="${g.id}">${escapeHtml(g.category)}</option>`);
            });
        }

        // Load grand options for a great id
        function loadGrandOptions(greatId) {
          if(!greatId) {
            document.getElementById('grandSelect').innerHTML = '<option value="">-- Select Grand --</option>';
            return;
          }
          fetch('get_grand.php?great_id='+greatId)
            .then(res => res.json())
            .then(data => {
              const grandSelect = document.getElementById('grandSelect');
              grandSelect.innerHTML = '<option value="">-- Select Grand --</option>';
              data.forEach(g=>grandSelect.innerHTML += `<option value="${g.id}">${escapeHtml(g.category)}</option>`);
            });
        }

        // Load parent options for a grand id
        function loadParentOptions(grandId) {
          if(!grandId) {
            document.getElementById('parentSelect').innerHTML = '<option value="">-- Select Parent --</option>';
            return;
          }
          fetch('get_parent.php?grand_id='+grandId)
            .then(res => res.json())
            .then(data => {
              const parentSelect = document.getElementById('parentSelect');
              parentSelect.innerHTML = '<option value="">-- Select Parent --</option>';
              data.forEach(p=>parentSelect.innerHTML += `<option value="${p.id}">${escapeHtml(p.category)}</option>`);
            });
        }

        // A minimal HTML escape for inserting server text into option elements
        function escapeHtml(str){
          if(!str) return '';
          return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // Event listeners for dynamic selects
        document.addEventListener('DOMContentLoaded', function(){
          // initialize lists
          refreshGreats();
          loadTree();

          document.getElementById('greatSelect').addEventListener('change', function(){
            const greatId = this.value;
            document.getElementById('parentGreatId').value = greatId;
            loadGrandOptions(greatId);
            // clear downstream selects
            document.getElementById('parentSelect').innerHTML = '<option value="">-- Select Parent --</option>';
          });

          document.getElementById('grandSelect').addEventListener('change', function(){
            const grandId = this.value;
            document.getElementById('childGrandId').value = grandId;
            document.getElementById('childGreatId').value = document.getElementById('parentGreatId').value;
            loadParentOptions(grandId);
          });

          // Expand/collapse toggles
          document.addEventListener('click', function(e){
            if(e.target.classList.contains('toggle')){
              let li = e.target.parentElement;
              li.classList.toggle('collapsed');
              e.target.textContent = li.classList.contains('collapsed') ? '▶' : '▼';
            }
          });

          // wire ajax submits
          ajaxSubmit('formGreat','msgGreat');
          ajaxSubmit('formGrand','msgGrand');
          ajaxSubmit('formParent','msgParent');
          ajaxSubmit('formChild','msgChild');
        });
    </script>

</body>
</html>
