<style>
    #sukiLogoutDialog { position:fixed; inset:0; margin:auto; width:calc(100% - 32px); max-width:400px; max-height:calc(100dvh - 32px); overflow:auto; padding:24px; border:1px solid #dce7e1; border-radius:8px; background:#fffefa; color:#173f35; box-shadow:0 18px 48px rgba(23,63,53,.18); font:inherit; }
    #sukiLogoutDialog::backdrop { background:rgba(20,42,34,.52); }
    #sukiLogoutDialog h2 { margin:0; font-size:17px; font-weight:600; line-height:1.45; }
    #sukiLogoutDialog p { margin:8px 0 24px; color:#66736d; font-size:13px; line-height:1.6; }
    #sukiLogoutDialog .suki-logout-actions { display:flex; justify-content:flex-end; gap:8px; }
    #sukiLogoutDialog button { min-height:40px; padding:9px 16px; border:1px solid #d7e1db; border-radius:6px; background:#fff; color:#294d40; font:inherit; font-size:13px; font-weight:600; cursor:pointer; }
    #sukiLogoutDialog button:hover { background:#f2f6f3; }
    #sukiLogoutDialog button[data-suki-logout-confirm] { border-color:#1f6f5b; background:#1f6f5b; color:#fff; }
    #sukiLogoutDialog button[data-suki-logout-confirm]:hover { background:#155244; }
    #sukiLogoutDialog button:focus-visible { outline:3px solid #8fcbb1; outline-offset:3px; }
    #sukiLogoutDialog button:disabled { opacity:.65; cursor:wait; }
</style>

<dialog id="sukiLogoutDialog" aria-labelledby="sukiLogoutTitle" aria-describedby="sukiLogoutDescription" data-suki-logout-dialog>
    <h2 id="sukiLogoutTitle">Are you sure you want to sign out?</h2>
    <p id="sukiLogoutDescription">You will be signed out of your account.</p>
    <div class="suki-logout-actions">
        <button type="button" data-suki-logout-cancel autofocus>Cancel</button>
        <button type="button" data-suki-logout-confirm>Sign Out</button>
    </div>
</dialog>
