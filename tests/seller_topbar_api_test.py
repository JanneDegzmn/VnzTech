import subprocess,json
for case,status in [('ok',200),('anonymous',401),('inactive',401),('post',405)]:
    raw=subprocess.check_output(['C:/xampp/php/php.exe','tests/seller_topbar_api_fixture.php',case],text=True)
    body,code=raw.rsplit('\nHTTP:',1)
    assert int(code)==status,(case,raw)
    data=json.loads(body)
    if case=='ok':
        assert data['updates']['total']==1 and data['updates']['items'][0]['reference']=='OWN',data
        assert 'OTHER' not in body
    else: assert data['success'] is False
print('4 isolated endpoint boundary cases passed (GET/session/inactive/owner scope); no application DB or session used.')
