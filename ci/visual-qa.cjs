const fs=require('fs'),path=require('path');
const {chromium}=require('./node_modules/playwright');
const cat=JSON.parse(fs.readFileSync('data/catalog.json','utf8'));
const pages=JSON.parse(fs.readFileSync('data/pages.json','utf8'));
fs.mkdirSync('qa/screens',{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const context=await browser.newContext({viewport:{width:1440,height:1000},deviceScaleFactor:1});
 const p=await context.newPage();const errors=[];p.on('pageerror',e=>errors.push(e.message));
 const base='http://127.0.0.1:8085';
 for(let attempt=0;attempt<30;attempt++){try{const r=await p.goto(base+'/');if(r.status()===200)break;}catch{}await p.waitForTimeout(200);}
 const urls={home:'/',catalog:'/catalog',cable:'/catalog/cable',metal:'/catalog/metal',profile:'/catalog/profile',transformers:'/catalog/transformers',about:'/about',delivery:'/delivery',documents:'/documents',faq:'/faq',contacts:'/contacts',search:'/search?q='+encodeURIComponent('ВВГ'),'request-empty':'/request',privacy:'/privacy',calculators:'/calculators',information:'/information',sitemap:'/sitemap','404':'/missing-page'};
 for(const root of ['cable','metal','profile','transformers'])urls['product-'+root]='/product/'+cat.products.find(x=>x.root_category===root).slug;
 urls['product-long']='/product/'+cat.products.reduce((a,b)=>a.name.length>b.name.length?a:b).slug;
 urls['product-placeholder']='/product/'+cat.products.find(x=>x.image.includes('placeholder')).slug;
 const layout=[];
 for(const [size,width] of [['desktop',1440],['mobile',390]]){
  await p.setViewportSize({width,height:size==='desktop'?1000:844});
  for(const [name,url] of Object.entries(urls)){
   const response=await p.goto(base+url,{waitUntil:'networkidle'});await p.evaluate(()=>document.fonts.ready);await p.screenshot({path:'qa/screens/'+size+'-'+name+'.png',fullPage:true});
   const data=await p.evaluate(()=>({scroll:document.documentElement.scrollWidth,width:innerWidth,broken:[...document.images].filter(x=>!x.complete||x.naturalWidth===0).map(x=>x.src)}));
   layout.push({size,name,status:response.status(),...data});console.log(size+' '+name+' overflow='+String(data.scroll>data.width));
  }
 }
 // Check every distinct imported body at a mobile width.
 const allPaths=[...cat.products.map(x=>'/product/'+x.slug),...cat.categories.map(x=>'/catalog/'+x.slug),...pages.filter(x=>!['home','404','success'].includes(x.slug)).map(x=>'/'+x.slug)];
 await p.setViewportSize({width:390,height:844});const bodyIssues=[];
 for(let i=0;i<allPaths.length;i++){
  const url=allPaths[i];await p.goto(base+url,{waitUntil:'load'});
  const data=await p.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,broken:[...document.images].filter(x=>x.complete&&x.naturalWidth===0).map(x=>x.src)}));
  if(data.scroll>data.width||data.broken.length){bodyIssues.push({url,...data});if(bodyIssues.length<25)await p.screenshot({path:'qa/screens/body-issue-'+bodyIssues.length+'.png',fullPage:true});}
  if((i+1)%100===0)console.log('Mobile bodies checked '+(i+1));
 }
 // Verify the real add-to-request interaction.
 await p.goto(base+urls['product-cable'],{waitUntil:'networkidle'});
 await p.locator('.buy-row [name=quantity]').fill('2');
 await p.locator('.buy-row button[type=submit]').click();
 await p.waitForFunction(()=>document.querySelector('[data-cart-count]').textContent==='1');
 await p.goto(base+'/request',{waitUntil:'networkidle'});
 for(const [size,width] of [['desktop',1440],['mobile',390]]){await p.setViewportSize({width,height:900});await p.screenshot({path:'qa/screens/'+size+'-request-filled.png',fullPage:true});}
 await p.goto(base+'/admin',{waitUntil:'networkidle'});await p.setViewportSize({width:1440,height:1000});await p.screenshot({path:'qa/screens/desktop-admin-login.png',fullPage:true});
 await p.locator('[name=username]').fill('admin');await p.locator('[name=password]').fill(fs.readFileSync('ci/.qa-password','utf8'));await p.locator('button[type=submit]').click();await p.waitForURL(/section=dashboard/);
 const adminUrls={dashboard:'dashboard',products:'products',categories:'categories',pages:'pages',faqs:'faqs',documents:'documents',media:'media',settings:'settings',statistics:'statistics',requests:'requests',users:'users',password:'password',audit:'audit','product-edit':'products&id=1','page-edit':'pages&id=2'};
 for(const [size,width] of [['desktop',1440],['mobile',390]]){
  await p.setViewportSize({width,height:size==='desktop'?1000:844});
  for(const [name,section] of Object.entries(adminUrls)){
   const r=await p.goto(base+'/admin?section='+section,{waitUntil:'networkidle'});await p.screenshot({path:'qa/screens/'+size+'-admin-'+name+'.png',fullPage:true});
   const data=await p.evaluate(()=>({scroll:document.documentElement.scrollWidth,width:innerWidth}));
   layout.push({size,name:'admin-'+name,status:r.status(),...data});console.log(size+' admin '+name+' overflow='+String(data.scroll>data.width));
  }
 }
 fs.writeFileSync('qa/visual-layout.json',JSON.stringify({browser:browser.version(),layout,bodyIssues,bodyCount:allPaths.length,errors},null,2));
 await browser.close();if(errors.length)process.exitCode=1;
})().catch(e=>{fs.writeFileSync('qa/visual-layout.json',JSON.stringify({fatal:String(e)},null,2));console.error(e);process.exit(1)});
