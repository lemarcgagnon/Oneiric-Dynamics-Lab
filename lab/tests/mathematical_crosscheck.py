#!/usr/bin/env python3
import json, math, random, subprocess, pathlib, sys
ROOT=pathlib.Path(__file__).resolve().parents[1]
rng=random.Random(20260916)
cases=[]; expected=[]
# Eq 3-4
for _ in range(60):
    rho=rng.random(); cf=rng.random(); cases.append({'type':'mixture','rho':rho,'cf':cf}); expected.append({'replay':1-rho,'recombination':rho*(1-cf),'counterfactual':rho*cf})
# Eq 6
for _ in range(60):
    vals=[rng.random()*2 for _ in range(6)]; n,c,a,ln,lc,la=vals
    cases.append({'type':'ranking','n':n,'c':c,'a':a,'ln':ln,'lc':lc,'la':la}); expected.append(ln*n-lc*c-la*a)
# Eq 7
for _ in range(40):
    scores=[rng.uniform(-4,4) for _ in range(rng.randint(1,8))]; t=rng.uniform(.05,2)
    mx=max(scores); ex=[math.exp((s-mx)/t) for s in scores]; den=sum(ex)
    cases.append({'type':'gibbs','scores':scores,'t':t}); expected.append([e/den for e in ex])
# Eq 19
for _ in range(50):
    d=rng.randint(2,10); x=[rng.uniform(-2,2) for _ in range(d)]; refs=[[rng.uniform(-2,2) for _ in range(d)] for __ in range(rng.randint(1,7))]; sigma=rng.uniform(.1,3); cap=rng.uniform(.1,3)
    val=min(cap,min(math.dist(x,r)/sigma for r in refs)); cases.append({'type':'novelty','x':x,'refs':refs,'sigma':sigma,'cap':cap}); expected.append(val)
# Eq 13
for _ in range(50):
    ref={'a':rng.random(),'b':rng.random()}; tol={'a':rng.random()*.2,'b':rng.random()*.2}; prop={'a':rng.random(),'b':rng.random()}
    cases.append({'type':'gate','ref':ref,'prop':prop,'tol':tol}); expected.append(all(prop[k] <= ref[k]+tol[k]+1e-12 for k in ref))
# Eq 12/18 distributions, common support
for _ in range(50):
    n=rng.randint(2,8); pa=[rng.random() for _ in range(n)]; qa=[rng.random() for _ in range(n)]; sp=sum(pa); sq=sum(qa); pa=[x/sp for x in pa]; qa=[x/sq for x in qa]; eta=rng.random(); m=[(1-eta)*p+eta*q for p,q in zip(pa,qa)]; tvpq=.5*sum(abs(q-p) for p,q in zip(pa,qa)); tvmp=.5*sum(abs(mm-p) for mm,p in zip(m,pa))
    cases.append({'type':'memory','p':pa,'q':qa,'eta':eta}); expected.append({'weights':m,'rhs':eta*tvpq,'tv':tvmp})
# Eq 8 / Eq 20 diagnostics
for _ in range(40):
    m=rng.randint(1,12); nov=[rng.random() for _ in range(m)]; admitted=[rng.random()>.35 for _ in range(m)]
    if not any(admitted): admitted[rng.randrange(m)]=True
    raw=[rng.random() if admitted[i] else 0.0 for i in range(m)]; den=sum(raw); weights=[(raw[i]/den if admitted[i] else 0.0) for i in range(m)]
    assessments=[{'candidate_uid':f'u{i}','experience_id':f'x{i}','admitted':admitted[i],'weight':weights[i],'novelty':nov[i]} for i in range(m)]
    qhat=[{'candidate_uid':f'u{i}','experience_id':f'x{i}','weight':weights[i]} for i in range(m) if admitted[i]]
    diag={'proposal_novelty':sum(nov)/m,'learning_novelty':sum(weights[i]*nov[i] for i in range(m) if admitted[i]),'admission_fraction':sum(admitted)/m}
    cases.append({'type':'diag','assessments':assessments}); expected.append({'qhat':qhat,'diag':diag})

proc=subprocess.run(['php',str(ROOT/'tests/math_probe.php')],input=json.dumps({'cases':cases}),text=True,capture_output=True,check=True)
actual=json.loads(proc.stdout)['results']
assert len(actual)==len(expected)
def close(a,b,tol=2e-10): return abs(a-b)<=tol*max(1,abs(a),abs(b))
for i,(a,e,c) in enumerate(zip(actual,expected,cases)):
    typ=c['type']
    if typ=='mixture':
        assert all(close(a[k],e[k]) for k in e), (i,typ,a,e)
        assert close(sum(a.values()),1.0)
    elif typ in ('ranking','novelty'): assert close(a,e),(i,typ,a,e)
    elif typ=='gibbs': assert len(a)==len(e) and all(close(float(x),y) for x,y in zip(a,e)) and close(sum(map(float,a)),1.0),(i,typ,a,e)
    elif typ=='gate': assert bool(a) is bool(e),(i,typ,a,e)
    elif typ=='diag':
        assert len(a['qhat'])==len(e['qhat']) and all(close(float(x['weight']),float(y['weight'])) and x['candidate_uid']==y['candidate_uid'] and x['experience_id']==y['experience_id'] for x,y in zip(a['qhat'],e['qhat'])),(i,typ,a,e)
        assert all(close(float(a['diag'][k]),float(e['diag'][k])) for k in e['diag']),(i,typ,a,e)
    elif typ=='memory':
        assert all(close(x,y) for x,y in zip(a['weights'],e['weights'])),(i,typ,a,e)
        assert close(a['tv']['tv_proposal_reference'],e['tv']) and close(a['tv']['rhs'],e['rhs']) and a['tv']['bound_pass'],(i,typ,a,e)
print(f'PASS independent Python↔PHP mathematical cross-check: {len(cases)} cases')
print('PASS Eq. (3)-(4), (6), (7), (8), (12), (13), (18), (19), (20) numeric equivalence')
