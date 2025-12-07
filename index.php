<?php
session_start();

// Admin list (usernames)
$admin_list = ['sev', 'system'];
// Users in this list can force private and delete mods they dont own.



$gaid = $_GET['gaid'] ?? $_COOKIE['gaid'] ?? '';
$user_identifier = null;
if($gaid){
    $api_url = 'https://gggravity.org/gravity-accounts/get-identifier.php?token=' . urlencode($gaid);
    $response = @file_get_contents($api_url);
    if($response && $response !== 'a') $user_identifier = trim($response);
}

function token_to_userid($token){
    if(!$token) return null;
    $api = 'https://gggravity.org/gravity-accounts/get-identifier.php?token=' . urlencode($token);
    $res = @file_get_contents($api);
    if($res && $res !== 'a') return trim($res);
    return null;
}
function userid_to_username($userid){
    if(!$userid) return $userid;
    $api = 'https://gggravity.org/gravity-accounts/getusernamefui.php?userid=' . urlencode($userid);
    $res = @file_get_contents($api);
    if($res && $res !== 'a') return trim($res);
    return $userid;
}

if(!$user_identifier){
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Login Required</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Lexend&display=swap" rel="stylesheet">';
    echo '<style>
    html, body {height:100%;margin:0;font-family:\'Lexend\',Arial,sans-serif;background:#000;display:flex;align-items:center;justify-content:center;color:#fff;}
    .login-container{text-align:center;background:#111;padding:40px 30px;border-radius:16px;box-shadow:0 0 40px rgba(0,0,0,0.5);max-width:400px;width:90%;animation:fadeIn 0.6s ease;}
    .login-container h2{margin-bottom:24px;font-size:22px;color:#fff;}
    .login-container button{padding:12px 24px;font-size:16px;font-weight:bold;color:#fff;background:linear-gradient(45deg,#4da6ff,#3399ff);border:none;border-radius:12px;cursor:pointer;transition:all 0.3s ease;}
    .login-container button:hover{background:linear-gradient(45deg,#3399ff,#1a8cff);transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.4);}
    @keyframes fadeIn{0%{opacity:0;transform:translateY(-20px);}100%{opacity:1;transform:translateY(0);}}
    </style></head><body>';
    echo '<div class="login-container"><h2>Please log in first</h2>';
    echo '<button onclick="window.location.href=\'https://gggravity.org/gravity-accounts/login?re=\' + encodeURIComponent(window.location.href)">Login</button></div></body></html>';
    exit;
}

if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$mods_file = __DIR__ . '/mods.json';
if(!file_exists($mods_file)) file_put_contents($mods_file, json_encode([]));
$mods = json_decode(file_get_contents($mods_file), true);
if(!is_array($mods)) $mods = [];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) exit('Invalid CSRF token');

    $is_admin_action = false;
    $admin_userid = null;
    $admin_username = null;
    if(!empty($_POST['admin_token'])){
        $provided_token = substr($_POST['admin_token'], 0, 200);
        $admin_userid = token_to_userid($provided_token);
        if($admin_userid){
            $admin_username = userid_to_username($admin_userid);
            if(in_array($admin_username, $admin_list)){
                $is_admin_action = true;
            } else {
                $is_admin_action = false;
            }
        }
    }

    $mod_id = $_POST['mod_id'] ?? uniqid();
    $existing_mod = $mods[$mod_id] ?? null;

    if(isset($_POST['delete']) && $_POST['delete']){
        if(!$existing_mod) exit('Mod not found');
        if($existing_mod['owner'] !== $user_identifier && !$is_admin_action) exit('Permission denied');
        unset($mods[$mod_id]);
        file_put_contents($mods_file, json_encode($mods, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
        exit('deleted');
    }

    if($existing_mod && $existing_mod['owner'] !== $user_identifier){
        $wants_change_shared = array_key_exists('shared', $_POST);
        $wants_change_name = array_key_exists('name', $_POST) && $_POST['name'] !== ($existing_mod['name'] ?? '');
        $wants_change_code = array_key_exists('code', $_POST) && $_POST['code'] !== ($existing_mod['code'] ?? '');
        $wants_change_description = array_key_exists('description', $_POST) && $_POST['description'] !== ($existing_mod['description'] ?? '');

        if($wants_change_name || $wants_change_code || $wants_change_description){
            exit('Permission denied');
        }

        if($wants_change_shared){
    if($existing_mod['owner'] !== $user_identifier && !$is_admin_action) exit('Permission denied');
} else {
            exit('No permitted changes');
        }
    } else {
        // existing mod is owned by current user OR creating new mod
    }

    $existing_mod = $mods[$mod_id] ?? null;

    $name = $_POST['name'] ?? ($existing_mod['name'] ?? 'Untitled Mod');
    $code = $_POST['code'] ?? ($existing_mod['code'] ?? '');
    if(strlen($code) > 50000) $code = substr($code, 0, 50000);

    $description = $_POST['description'] ?? ($existing_mod['description'] ?? '');
    if(mb_strlen($description) > 100) $description = mb_substr($description, 0, 100);

    if(array_key_exists('shared', $_POST)){
        $shared_raw = $_POST['shared'];
        $shared = ($shared_raw === '0' || $shared_raw === 0 || $shared_raw === 'false') ? false : (bool)$shared_raw;
    } else {
        $shared = $existing_mod['shared'] ?? false;
    }

    $owner = $existing_mod['owner'] ?? $user_identifier;

    $mods[$mod_id] = [
        'name' => $name,
        'owner' => $owner,
        'shared' => $shared,
        'code' => $code,
        'description' => $description
    ];

    file_put_contents($mods_file, json_encode($mods, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Basic Mod Manager</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#000;--panel:#111;--muted:#222;--accent:#4da6ff;--danger:#ff5555;--admin:#ffaa00;color-scheme:dark}
*{box-sizing:border-box}
html,body{height:100%;margin:0;padding:0;background:var(--bg);color:#fff;font-family:'Lexend',Arial,Helvetica,sans-serif}
header{position:fixed;top:0;left:0;right:0;height:72px;background:var(--panel);display:flex;align-items:center;justify-content:space-between;padding:10px 20px;z-index:1100}
main{padding-top:1px;width:92%;max-width:1100px;margin:0 auto}
.tabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:nowrap;overflow-x:auto;white-space:nowrap}
.tab-btn{padding:8px 16px;background:var(--muted);border:none;color:#fff;border-radius:8px;cursor:pointer;transition:0.2s;flex: 1 1 auto; text-align:center;}
.tab-btn.active{background:var(--accent)}
.tab-btn:hover:not(.active){background:#333}
.tab-content{display:none}
.tab-content.active{display:block}
ul{list-style:none;padding:0;margin:0}
ul li{margin-bottom:10px}
ul li a{color:var(--accent);text-decoration:none}
ul li a:hover{text-decoration:underline}

form{background:transparent;padding:0;margin:0}
.input, input, textarea, select, button{width:100%;margin:8px 0;padding:10px;background:var(--muted);border:none;border-radius:8px;color:#fff;box-sizing:border-box}
textarea{height:200px;resize:vertical;font-family:Courier,monospace}
.input-row{display:flex;gap:8px}
.input-row > *{flex:1}
label.inline{display:flex;align-items:center;gap:8px;margin:8px 0}
small.note{display:block;color:#aaa;font-size:13px;margin-top:4px}

#viewer {margin-top:12px}
#viewer h3{margin:4px 0 8px 0}
#viewer pre{background:#0f0f0f;padding:16px;border-radius:8px;white-space:pre-wrap;word-break:break-word;margin-top:12px}
#admin-controls{display:flex;gap:8px;margin-top:12px}
.btn{cursor:pointer;transition:0.12s;background:var(--accent);border-radius:8px;padding:10px 12px;border:none;color:#fff}
.btn:hover{background:#3399ff}
.btn-danger{background:var(--danger)}
.btn-admin{background:var(--admin);color:#000}
.search{padding:8px 10px;background:#1b1b1b;border-radius:8px;border:1px solid transparent;color:#fff}

@media (max-width:640px){.tabs{overflow:auto;padding-bottom:8px}}
</style>
</head>
<body>
<main>
  <div class="tabs">
    <button class="tab-btn active" data-tab="shared">Shared Mods</button>
    <button class="tab-btn" data-tab="my">My Mods</button>
    <button class="tab-btn" data-tab="create">Create Mod</button>
    <button class="tab-btn" data-tab="edit">Edit Mod</button>
    <button class="tab-btn" data-tab="docs">Docs</button>
  </div>

  <div id="shared" class="tab-content active">
    <input id="shared-search" class="search" placeholder="Search shared mods..." />
    <ul id="shared-list"></ul>
  </div>

  <div id="my" class="tab-content">
    <input id="my-search" class="search" placeholder="Search my mods..." />
    <ul id="my-list"></ul>
  </div>

  <div id="create" class="tab-content">
    <form id="create-form" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?>">
      <input class="input" name="name" placeholder="Mod Name" required>
      <textarea class="input" name="description" placeholder="Description (optional, max 100 characters)" rows="3" maxlength="100"></textarea>
      <textarea class="input" name="code" placeholder="Initial Mod Code (optional)"></textarea>
      <div style="display:flex;gap:8px"><button class="btn" type="submit">Create Mod</button></div>
    </form>
  </div>

  <div id="edit" class="tab-content">
    <form id="edit-form" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?>">
      <select id="edit_mod_select" class="input" name="mod_id"></select>
      <input type="hidden" id="edit_mod_id" name="mod_id_hidden" value="">
      <textarea class="input" id="edit_mod_code" name="code" placeholder="Mod Code"></textarea>
      <input type="hidden" name="shared" value="0">
      <label class="inline"><span>Shared:</span><input id="edit_mod_shared" type="checkbox" name="shared" value="1"></label>
      <textarea class="input" id="edit_mod_description" name="description" placeholder="Description (optional, max 100 characters)" rows="3" maxlength="100"></textarea>
      <div style="display:flex;gap:8px">
        <button class="btn" id="save_edit_btn" type="submit">Save Changes</button>
        <button class="btn btn-danger" id="delete_mod_btn" type="button">Delete Mod</button>
      </div>
    </form>
  </div>

  <div id="docs" class="tab-content">
    <h2>Documentation</h2>
    <p>Welcome to basic mod manger modding Docs.</p>
    <h3>Quick Start</h3>
    <ol>
      <li>Create a new mod in the Create tab.</li>
      <li>Edit code and description in the Edit tab.</li>
      <li>Toggle shared to publish it to Shared Mods.</li>
    </ol>
  </div>

  <div id="viewer" class="tab-content">
    <h3 id="viewer_name"></h3>
    <p id="viewer_description"></p>
    <p><strong>Creator:</strong> <span id="viewer_owner"></span></p>
    <div id="admin-controls"></div>
    <pre id="viewer_code"></pre>
  </div>
</main>

<script>
window.addEventListener('DOMContentLoaded', function(){
  let mods = <?php echo json_encode($mods, JSON_UNESCAPED_UNICODE); ?> || {};
  const session_user_id = '<?php echo addslashes($user_identifier); ?>';
  const adminList = <?php echo json_encode($admin_list); ?> || [];
  const csrfToken = '<?php echo addslashes($csrf_token); ?>';

  function readGaidToken(){
    const cookieMatch = document.cookie.split(';').map(c=>c.trim()).find(c=>c.startsWith('gaid='));
    if(cookieMatch) return cookieMatch.split('=')[1];
    try{ const ls = localStorage.getItem('gaid'); if(ls) return ls; }catch(e){}
    return '';
  }

  const usernamesCache = {};
  async function getUsername(id){
    if(!id) return id;
    if(usernamesCache[id]) return usernamesCache[id];
    try{
      const res = await fetch('https://gggravity.org/gravity-accounts/getusernamefui.php?userid=' + encodeURIComponent(id));
      const text = await res.text();
      usernamesCache[id] = (text === 'a' ? id : text);
      return usernamesCache[id];
    }catch(e){
      return id;
    }
  }

  const sharedList = document.getElementById('shared-list');
  const myList = document.getElementById('my-list');
  const sharedSearch = document.getElementById('shared-search');
  const mySearch = document.getElementById('my-search');
  const editSelect = document.getElementById('edit_mod_select');
  const editIdHidden = document.getElementById('edit_mod_id');
  const editCode = document.getElementById('edit_mod_code');
  const editShared = document.getElementById('edit_mod_shared');
  const editDescription = document.getElementById('edit_mod_description');
  const viewerName = document.getElementById('viewer_name');
  const viewerDescription = document.getElementById('viewer_description');
  const viewerOwner = document.getElementById('viewer_owner');
  const viewerCode = document.getElementById('viewer_code');
  const adminControls = document.getElementById('admin-controls');

  const tabButtons = document.querySelectorAll('.tab-btn');
  tabButtons.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      tabButtons.forEach(b=>b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
      btn.classList.add('active');
      const tab = btn.dataset.tab;
      document.getElementById(tab).classList.add('active');
      if(tab === 'edit') populateEditDropdown();
    });
  });

  const moreBtn = document.getElementById('more-btn'), moreMenu = document.getElementById('more-menu');
  document.addEventListener('click', (e)=>{
    if(moreBtn.contains(e.target)) moreMenu.classList.toggle('show');
    else if(!moreMenu.contains(e.target)) moreMenu.classList.remove('show');
  });

  function escapeHtml(s){ if(!s) return ''; return s.replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function refreshLists(){
    sharedList.innerHTML = '';
    myList.innerHTML = '';
    const sharedEntries = [];
    const myEntries = [];
    for(const id in mods){
      const mod = mods[id];
      if(mod && mod.shared) sharedEntries.push({id,mod});
      if(mod && mod.owner === session_user_id) myEntries.push({id,mod});
    }
    for(const e of sharedEntries){
      const username = await getUsername(e.mod.owner);
      const li = document.createElement('li');
      const a = document.createElement('a'); a.href='#'; a.textContent = e.mod.name;
      a.addEventListener('click', ev=>{ ev.preventDefault(); openMod(e.id); });
      li.appendChild(a);
      li.insertAdjacentHTML('beforeend', ' (' + escapeHtml(username) + ')');
      sharedList.appendChild(li);
    }
    for(const e of myEntries){
      const li = document.createElement('li');
      const a = document.createElement('a'); a.href='#'; a.textContent = e.mod.name;
      a.addEventListener('click', ev=>{ ev.preventDefault(); openMod(e.id); });
      li.appendChild(a);
      myList.appendChild(li);
    }
  }

  function populateEditDropdown(){
    editSelect.innerHTML = '<option value="">-- select a mod to edit --</option>';
    for(const id in mods){
      const mod = mods[id];
      if(mod.owner === session_user_id){
        const opt = document.createElement('option'); opt.value = id; opt.textContent = mod.name;
        editSelect.appendChild(opt);
      }
    }
    if(editSelect.options.length > 1){
      editSelect.selectedIndex = 1;
      selectMod(editSelect.value);
    } else {
      editIdHidden.value=''; editCode.value=''; editShared.checked=false; editDescription.value='';
    }
  }

  function selectMod(id){
    if(!id || !mods[id]) return;
    const mod = mods[id];
    editSelect.value = id;
    editIdHidden.value = id;
    editCode.value = mod.code ?? '';
    editShared.checked = !!mod.shared;
    editDescription.value = mod.description ?? '';
  }

  async function openMod(id){
    if(!mods[id]) return;
    const mod = mods[id];

    if(mod.owner === session_user_id){
      tabButtons.forEach(b=>b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
      document.querySelector('.tab-btn[data-tab="edit"]').classList.add('active');
      document.getElementById('edit').classList.add('active');
      populateEditDropdown(); selectMod(id); return;
    }

    tabButtons.forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
    document.getElementById('viewer').classList.add('active');

    viewerName.textContent = mod.name;
    viewerDescription.textContent = mod.description ?? '';
    viewerCode.textContent = mod.code ?? '';

    const ownerName = await getUsername(mod.owner);
    viewerOwner.textContent = ownerName;

    // admin controls
    adminControls.innerHTML = '';
    const gaid = readGaidToken();
    if(!gaid) return;
    
    try{
      const idRes = await fetch('https://gggravity.org/gravity-accounts/get-identifier.php?token=' + encodeURIComponent(gaid));
      const idText = await idRes.text();
      if(!idText || idText === 'a') return;
      const adminUserid = idText.trim();
      const nameRes = await fetch('https://gggravity.org/gravity-accounts/getusernamefui.php?userid=' + encodeURIComponent(adminUserid));
      const nameText = await nameRes.text();
      const adminUsername = (nameText === 'a' ? adminUserid : nameText.trim());
      if(!adminList.includes(adminUsername)) return;

      const forcePrivateBtn = document.createElement('button');
      forcePrivateBtn.className = 'btn btn-admin'; forcePrivateBtn.textContent = 'Force Private';
      forcePrivateBtn.addEventListener('click', ()=>{
        const body = new URLSearchParams();
        body.append('csrf_token', csrfToken);
        body.append('mod_id', id);
        body.append('shared', '0');
        body.append('admin_token', gaid);
        fetch(window.location.href, {method:'POST', body})
          .then(()=>location.reload()).catch(()=>location.reload());
      });

      const deleteBtn = document.createElement('button');
      deleteBtn.className = 'btn btn-danger'; deleteBtn.textContent = 'Delete';
      deleteBtn.addEventListener('click', ()=>{
        if(!confirm('Delete this mod?')) return;
        const body = new URLSearchParams();
        body.append('csrf_token', csrfToken);
        body.append('mod_id', id);
        body.append('delete', '1');
        body.append('admin_token', gaid);
        fetch(window.location.href, {method:'POST', body})
          .then(()=>location.reload()).catch(()=>location.reload());
      });

      adminControls.appendChild(forcePrivateBtn);
      adminControls.appendChild(deleteBtn);
    }catch(e){
      return;
    }
  }

  const editForm = document.getElementById('edit-form');
  editForm.addEventListener('submit', function(e){
    const chosenId = editSelect.value || editIdHidden.value;
    if(!chosenId){ e.preventDefault(); alert('Select a mod to edit first.'); return; }
    editIdHidden.name = 'mod_id';
    editIdHidden.value = chosenId;
  });

  document.getElementById('delete_mod_btn').addEventListener('click', function(){
    const modId = editSelect.value || editIdHidden.value;
    if(!modId){ alert('Select a mod to delete.'); return; }
    if(!confirm('Delete this mod?')) return;
    const body = new URLSearchParams();
    body.append('csrf_token', csrfToken);
    body.append('mod_id', modId);
    body.append('delete', '1');
    fetch(window.location.href, {method:'POST', body})
      .then(()=>location.reload()).catch(()=>location.reload());
  });

  editSelect.addEventListener('change', function(e){ const id = e.target.value; if(id) selectMod(id); });

  sharedSearch.addEventListener('input', function(){ const f = sharedSearch.value.trim().toLowerCase(); document.querySelectorAll('#shared-list li').forEach(li=>li.style.display = li.textContent.toLowerCase().includes(f)?'':'none');});
  mySearch.addEventListener('input', function(){ const f = mySearch.value.trim().toLowerCase(); document.querySelectorAll('#my-list li').forEach(li=>li.style.display = li.textContent.toLowerCase().includes(f)?'':'none');});

  (async function init(){ await refreshLists(); populateEditDropdown();
    (function(){ const params = new URLSearchParams(window.location.search); const gaid = params.get('gaid'); if(gaid){ try{ localStorage.setItem('gaid', gaid);}catch(e){} params.delete('gaid'); const newUrl = window.location.pathname + (params.toString()?('?'+params.toString()):''); window.history.replaceState({}, '', newUrl); document.cookie = 'gaid=' + gaid + '; path=/'; } })();
  })();

  async function refreshLists(){
    sharedList.innerHTML=''; myList.innerHTML='';
    const sharedEntries=[]; const myEntries=[];
    for(const id in mods){
      const mod = mods[id];
      if(mod && mod.shared) sharedEntries.push({id,mod});
      if(mod && mod.owner === session_user_id) myEntries.push({id,mod});
    }
    for(const e of sharedEntries){
      const username = await getUsername(e.mod.owner);
      const li = document.createElement('li'); const a = document.createElement('a'); a.href='#'; a.textContent = e.mod.name;
      a.addEventListener('click', ev=>{ ev.preventDefault(); openMod(e.id); });
      li.appendChild(a); li.insertAdjacentHTML('beforeend',' ('+escapeHtml(username)+')'); sharedList.appendChild(li);
    }
    for(const e of myEntries){
      const li = document.createElement('li'); const a = document.createElement('a'); a.href='#'; a.textContent = e.mod.name;
      a.addEventListener('click', ev=>{ ev.preventDefault(); openMod(e.id); });
      li.appendChild(a); myList.appendChild(li);
    }
  }
});
</script>
</body>
</html>
