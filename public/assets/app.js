const csrf=document.body.dataset.csrf||'';
const q=s=>document.querySelector(s);
const i18n=window.ODLAB_I18N||{};
const tr=(k,vars={})=>{let s=i18n[k]||k;for(const [a,b] of Object.entries(vars))s=s.replaceAll(`{${a}}`,String(b));return s;};
let previewTimer=null;
function readMission(includeKey=false){
  const od={rho:+q('#rho').value,counterfactual_share:+q('#cfshare').value,anchor_weight:0};
  document.querySelectorAll('[data-key]').forEach(x=>od[x.dataset.key]=+x.value);
  const payload={title:q('#title').value,objective:q('#objective').value,suite:q('#suite').value,seed_count:+q('#seedCount').value,base_seed:101,
    config:{od,benchmark:{active_memory_capacity:+q('#memoryCapacity').value},budget:{max_api_calls:+q('#maxApiCalls').value,max_input_tokens:500000,max_output_tokens:+q('#maxOutputTokens').value}}};
  if(includeKey)payload.api_key=q('#apiKey').value;
  return payload;
}
function updateOutputs(){
  q('#rhoOut').value=(+q('#rho').value).toFixed(2);q('#cfOut').value=(+q('#cfshare').value).toFixed(2);
  document.querySelectorAll('.slider input[type=range]').forEach(x=>{const o=x.parentElement.querySelector('output');if(o&&!['rhoOut','cfOut'].includes(o.id))o.value=x.value;});
}
function updateKeyBadge(){const has=(q('#apiKey')?.value||'').trim().length>0;const b=q('#keyBadge');if(!b)return;b.textContent=has?tr('api.key_present_session'):tr('api.key_missing');b.className='badge '+(has?'good':'warn');}
async function preview(){
  updateOutputs();const st=q('#previewStatus');st.textContent=tr('preflight.validating');st.className='badge';
  try{
    const r=await fetch('api.php?action=preview',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(readMission(false))});
    const j=await r.json();if(!j.ok)throw new Error(j.error);
    q('#alpha').textContent=(+j.mix.replay).toFixed(3);q('#beta').textContent=(+j.mix.recombination).toFixed(3);q('#gamma').textContent=(+j.mix.counterfactual).toFixed(3);
    q('#conditionCount').textContent=tr('preflight.conditions',{n:j.condition_count});q('#apiEstimate').textContent=tr('preflight.calls',{base:j.estimated_api_calls.baseline,worst:j.estimated_api_calls.worst_case_attempts});
    const budget=+q('#maxApiCalls').value;st.textContent=budget<j.estimated_api_calls.baseline?tr('preflight.insufficient'):(budget<j.estimated_api_calls.worst_case_attempts?tr('preflight.retry_margin'):tr('preflight.valid'));st.className='badge '+(budget<j.estimated_api_calls.baseline?'failed':budget<j.estimated_api_calls.worst_case_attempts?'warn':'good');
  }catch(e){st.textContent=tr('preflight.invalid',{error:e.message});st.className='badge failed';}
}
function schedulePreview(){updateOutputs();clearTimeout(previewTimer);previewTimer=setTimeout(preview,180);}
document.querySelectorAll('input[type=range],#suite').forEach(x=>x.addEventListener('input',schedulePreview));
q('#apiKey')?.addEventListener('input',updateKeyBadge);
q('#forgetKeyBtn')?.addEventListener('click',()=>{q('#apiKey').value='';updateKeyBadge();q('#apiKey').focus();});
q('#runBtn')?.addEventListener('click',async()=>{
  const btn=q('#runBtn'),status=q('#runStatus');
  if(!(q('#apiKey').value||'').trim()){status.textContent=tr('api.key_required_run');updateKeyBadge();q('#apiKey').focus();return;}
  btn.disabled=true;status.textContent=tr('run.running');
  try{
    const payload=readMission(true);
    const r=await fetch('api.php?action=create_run',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(payload)});
    payload.api_key='';
    const j=await r.json();if(!j.ok)throw new Error(j.error);
    status.innerHTML=`${tr('run.done')} <a href="report.php?id=${encodeURIComponent(j.run_id)}">${tr('run.open_report')}</a> · <a href="export.php?id=${encodeURIComponent(j.run_id)}&format=json">${tr('run.package')}</a>`;
  }catch(e){status.textContent=tr('run.failed',{error:e.message});}finally{btn.disabled=false;}
});
updateOutputs();updateKeyBadge();
