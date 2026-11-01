<script>
document.addEventListener('DOMContentLoaded', () => {
    const form=document.getElementById('rrRecommendation');
    if (!form) return;
    const dialog=document.getElementById('rrConfirm'),submit=document.getElementById('rrSubmit'),confirm=document.getElementById('rrConfirmSubmit'),cancel=document.getElementById('rrCancel');
    const recommendation=document.getElementById('rrRecommendationValue'),note=document.getElementById('rrNote'),error=document.getElementById('rrSubmitError'),status=document.getElementById('rrSubmitStatus');
    let pending=false,complete=false;
    const feedback=text=>{error.textContent=text;error.hidden=!text;};
    function busy(value) {
        pending=value;[submit,confirm,cancel,recommendation,note].forEach(control=>control.disabled=value);
        form.setAttribute('aria-busy',String(value));
    }
    form.addEventListener('submit',event=>{
        event.preventDefault();if(pending || complete || !form.reportValidity())return;
        feedback('');document.getElementById('rrConfirmChoice').textContent=recommendation.value==='APPROVE'?'Recommend approval':'Recommend rejection';
        if(!dialog.open)dialog.showModal();cancel.focus();
    });
    cancel.addEventListener('click',()=>{if(!pending)dialog.close();});
    dialog.addEventListener('cancel',event=>{if(pending)event.preventDefault();});
    dialog.addEventListener('close',()=>{if(!complete)submit.focus();});
    confirm.addEventListener('click',async()=>{
        if(pending || complete || !dialog.open || !form.reportValidity())return;
        const data={recommendation:recommendation.value,note:note.value};busy(true);feedback('');
        status.hidden=false;status.textContent='Submitting recommendation…';
        try {
            const response=await fetch(form.action,{method:'PATCH',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:JSON.stringify(data)});
            if(response.status===422){const payload=await response.json();feedback(Object.values(payload.errors??{}).flat().join(' ') || 'Please review your recommendation.');return;}
            if([401,403,419].includes(response.status)){feedback('Your session or access changed. Refresh the page before retrying.');return;}
            if(!response.ok)throw new Error('Unconfirmed response');
            complete=true;status.textContent='Recommendation submitted. Admin review is required.';window.location.reload();
        } catch (_) {feedback('Submission could not be confirmed. Refresh to check the request before retrying. Your explanation is still here.');}
        finally {if(!complete){busy(false);status.hidden=true;dialog.close();}}
    });
});
</script>
