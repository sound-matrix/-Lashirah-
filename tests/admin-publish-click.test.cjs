const fs=require('fs');
const vm=require('vm');
const assert=require('assert').strict;

(async function(){
  const html=fs.readFileSync('/tmp/lashirah-admin-page.html','utf8');
  const match=html.match(/<script id="admin-publish-click-controller">([\s\S]*?)<\/script>/);
  assert(match,'Admin click controller not found');
  const scripts={};
  let listener=null,fetchCount=0,request=null;
  const notice={textContent:'',dataset:{},style:{display:'none'}};
  const button={disabled:false,textContent:'✓ Publish to website',dataset:{}};
  const doc={
    getElementById:function(id){return id==='admin-live-publish-status'?notice:null;},
    querySelectorAll:function(){return [button];},
    addEventListener:function(name,fn,capture){if(name==='click'&&capture===true)listener=fn;}
  };
  const data={medals:{gold:{count:3}},gallery:[],videos:[]};
  const ctx={
    document:doc,
    window:{location:{origin:'https://admin.lashirah.com'},__CMS_TOKEN:'test-token',
      CMS:{data:()=>data,getPendingImages:()=>({})}},
    CMS:{data:()=>data,getPendingImages:()=>({})},
    fetch:async function(url,options){
      fetchCount++;request={url,options};
      return {ok:true,status:200,text:async()=>JSON.stringify({ok:true})};
    },
    console:console,JSON:JSON,Array:Array,Error:Error,String:String,Promise:Promise
  };
  vm.runInNewContext(match[1],ctx,{timeout:5000});
  assert.equal(typeof listener,'function','Click capture listener not attached');
  let prevented=false,stopped=false;
  listener({target:{closest:(selector)=>selector==='#admin [data-act="dl"]'?button:null},
    preventDefault(){prevented=true;},stopImmediatePropagation(){stopped=true;}});
  await new Promise(resolve=>setImmediate(resolve));
  assert.equal(prevented,true,'Click default was not stopped');
  assert.equal(stopped,true,'Old download action was not intercepted');
  assert.equal(fetchCount,1,'Publish button did not issue one request');
  assert.equal(request.url,'https://admin.lashirah.com/publish.php');
  assert.equal(request.options.credentials,'same-origin');
  assert.equal(request.options.headers['X-CSRF-Token'],'test-token');
  assert.equal(JSON.parse(request.options.body).data.gallery.length,0);
  assert.equal(button.disabled,false,'Button was not re-enabled');
  assert.equal(notice.dataset.state,'success');
  assert.match(notice.textContent,/Published successfully/);
  console.log('Real DOM click handler simulation passed: admin-origin POST, session and CSRF, success feedback, click interception.');
})().catch(e=>{console.error(e);process.exit(1);});
