import {readFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const manifest=JSON.parse(await readFile('tests/site_status_routes.json','utf8'));
const sellerRoutes=new Set(manifest.seller_routes);
const sellerScopes=manifest.seller_service_scopes||{};
const sharedScopes=manifest.shared_service_scopes||{};
const publicScopes=manifest.public_service_scopes||{};
const actionScopes=manifest.action_scopes||{};
let checks=0;
for(const [path,[format,admin]] of Object.entries(manifest.routes)){
    const source=await readFile(path,'utf8');
    const actionScope=actionScopes[path];
    if(actionScope){
        assert.ok(!sellerRoutes.has(path),`${path}: mixed-action route is not globally seller-only`);
        for(const [scope,actions] of [['seller',actionScope.seller],['admin',actionScope.admin]]){
            const actionList=actions.map(action=>`'${action}'`).join(', ');
            assert.ok(source.includes(`in_array($action, [${actionList}], true)`),`${path}: explicit ${scope} action list`);
            const call=scope==='admin'
                ?`vnzEnforceSiteMaintenance('${format}', true);`
                :`vnzEnforceSiteMaintenance('${format}', false, true, '${actionScope.service_mode}');`;
            assert.ok(source.includes(call),`${path}: ${scope} actions use the correct guard`);
        }
        assert.equal(actionScope.fallback,'public',`${path}: unknown actions retain public fallback behavior`);
        const fallback=`vnzEnforceSiteMaintenance('${format}', false);`;
        assert.ok(source.includes(fallback)&&/}\s*else\s*{/.test(source),`${path}: explicit public fallback guard`);
        const dbIndex=source.indexOf("require_once __DIR__ . '/database.php'");
        const guardIndex=Math.min(...[
            source.indexOf(`vnzEnforceSiteMaintenance('${format}', true);`),
            source.indexOf(`vnzEnforceSiteMaintenance('${format}', false, true);`),
            source.indexOf(fallback)
        ].filter(index=>index>=0));
        assert.ok(guardIndex>=0&&(dbIndex<0||guardIndex<dbIndex),`${path}: action guards precede database`);
        checks+=7;
        continue;
    }
    const seller=sellerRoutes.has(path);
    assert.ok(!seller||!admin,`${path}: seller-only and admin scopes do not overlap`);
    const serviceScope=sellerScopes[path]||sharedScopes[path]||publicScopes[path];
    const call=serviceScope
        ?`vnzEnforceSiteMaintenance('${format}', ${admin}, ${seller||!!sharedScopes[path]}, '${serviceScope}');`
        :seller
            ?`vnzEnforceSiteMaintenance('${format}', ${admin}, true);`
            :`vnzEnforceSiteMaintenance('${format}', ${admin});`;
    assert.ok(source.includes(call),`${path}: explicit correct guard`);
    assert.ok(source.indexOf(call)<source.indexOf("require_once __DIR__ . '/database.php'")||!source.includes("require_once __DIR__ . '/database.php'"),`${path}: guard precedes database`);
    checks+=2;
}
for(const path of sellerRoutes){
    assert.ok(Object.prototype.hasOwnProperty.call(manifest.routes,path),`${path}: seller scope is in the route inventory`);
    assert.ok(Object.prototype.hasOwnProperty.call(sellerScopes,path),`${path}: seller service mode is assigned`);
    checks+=2;
}
for(const [path,scope] of Object.entries(sharedScopes)){
    assert.equal(manifest.routes[path]?.[1],true,`${path}: shared service route keeps Admin access`);
    assert.ok(scope==='messaging',`${path}: shared service mode is explicit`);
    checks+=2;
}
for(const [path,scope] of Object.entries(publicScopes)){
    assert.equal(manifest.routes[path]?.[1],false,`${path}: public service route is not Admin-only`);
    assert.ok(scope==='sell_page',`${path}: landing page is separately scoped`);
    checks+=2;
}
for(const path of manifest.empty_nonfeatures){assert.equal((await readFile(path,'utf8')).trim(),'');checks++;}
const service=await readFile('backend/site_status.php','utf8');assert.ok(service.includes('if (getCurrentAdmin() !== null)'));assert.ok(!service.includes('if (isAdminLoggedIn())'));assert.ok(service.includes('vnzSiteMaintenanceAffectsScope($state, $serviceScope)'));checks+=3;
const sellerShell=await readFile('account/dashboard.php','utf8');assert.ok(sellerShell.includes("status.code === 'maintenance'"));assert.ok(sellerShell.includes('portal-error portal-maintenance'));checks+=2;
const client=await readFile('assets/js/site-status.js','utf8');assert.ok(!client.includes('admin/login.php'));assert.ok(!client.includes('Admin sign in'));assert.ok(client.includes('maintenanceAffectsPage(maintenance)'));checks+=3;
console.log(`PASS ${checks} explicit maintenance boundary/source assertions; ${Object.keys(manifest.routes).length} guarded HTTP entries, ${sellerRoutes.size} seller routes mapped to service modes, ${Object.keys(sharedScopes).length} shared Admin/seller messaging routes, and ${Object.keys(publicScopes).length} public landing scopes.`);
