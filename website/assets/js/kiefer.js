/* ============================================================================
   Kiefer-Sektion v2: Unterkiefer mit Lücke. Beim Scrollen wird zuerst die
   Implantatschraube eingedreht, danach setzt sich die Krone auf.
   three.js r128 (UMD, lokal). Prozedurale Umgebungsspiegelung für den
   glänzenden Scan-Look, keine externen Dateien.
   ============================================================================ */
(function () {
  'use strict';
  var wurzel = document.querySelector('[data-kiefer]');
  if (!wurzel || !window.THREE) return;
  var canvas = wurzel.querySelector('canvas');
  var fallback = wurzel.querySelector('.kiefer__fallback');
  var fortschrittEl = wurzel.querySelector('.kiefer__balken span');
  var schritte = Array.prototype.slice.call(document.querySelectorAll('[data-kiefer-schritt]'));
  var reduziert = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var renderer;
  try { renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true, powerPreference: 'high-performance' }); }
  catch (e) { renderer = null; }
  if (!renderer) { if (fallback) fallback.hidden = false; canvas.hidden = true; return; }
  if (fallback) fallback.hidden = true;

  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
  renderer.outputEncoding = THREE.sRGBEncoding;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 0.72;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.physicallyCorrectLights = true;

  var szene = new THREE.Scene();
  var kamera = new THREE.PerspectiveCamera(30, 1, 0.1, 100);
  kamera.position.set(0.25, 4.0, 9.6);
  kamera.lookAt(0, 0.05, 0.5);

  /* ---------- Umgebung (Studio-Softbox, prozedural) ---------- */
  function umgebung() {
    var c = document.createElement('canvas'); c.width = 1024; c.height = 512;
    var g = c.getContext('2d');
    var grad = g.createLinearGradient(0, 0, 0, 512);
    grad.addColorStop(0, '#ffffff'); grad.addColorStop(0.45, '#dfe7f1'); grad.addColorStop(0.55, '#c9d5e3'); grad.addColorStop(1, '#8f9db0');
    g.fillStyle = grad; g.fillRect(0, 0, 1024, 512);
    // zwei Softboxen (helle Rechtecke) und ein blauer Reflex
    g.fillStyle = 'rgba(255,255,255,.95)'; g.fillRect(120, 60, 260, 120);
    g.fillStyle = 'rgba(255,255,255,.7)'; g.fillRect(640, 90, 300, 90);
    g.fillStyle = 'rgba(43,135,218,.35)'; g.fillRect(420, 300, 220, 60);
    var tex = new THREE.CanvasTexture(c);
    tex.mapping = THREE.EquirectangularReflectionMapping;
    tex.encoding = THREE.sRGBEncoding;
    var pmrem = new THREE.PMREMGenerator(renderer);
    var env = pmrem.fromEquirectangular(tex).texture;
    pmrem.dispose(); tex.dispose();
    return env;
  }
  szene.environment = umgebung();

  /* ---------- Licht ---------- */
  szene.add(new THREE.HemisphereLight(0xffffff, 0x8f9db0, 0.45));
  var sonne = new THREE.DirectionalLight(0xffffff, 1.9);
  sonne.position.set(3, 8, 5);
  sonne.castShadow = true;
  sonne.shadow.mapSize.set(1024, 1024);
  sonne.shadow.camera.near = 1; sonne.shadow.camera.far = 30;
  sonne.shadow.camera.left = -6; sonne.shadow.camera.right = 6;
  sonne.shadow.camera.top = 6; sonne.shadow.camera.bottom = -6;
  sonne.shadow.radius = 6; sonne.shadow.bias = -0.0005;
  szene.add(sonne);
  var fuell = new THREE.DirectionalLight(0xdbe9f7, 0.8); fuell.position.set(-6, 3, -2); szene.add(fuell);
  var kante = new THREE.DirectionalLight(0xeaf4ff, 0.95); kante.position.set(-2.5, 2.2, -6); szene.add(kante);
  var kante2 = new THREE.DirectionalLight(0xffffff, 0.55); kante2.position.set(5, 1.5, -4); szene.add(kante2);

  /* ---------- Materialien ---------- */
  var matZahn = new THREE.MeshPhysicalMaterial({ color: 0xf6efe1, roughness: 0.16, metalness: 0, clearcoat: 0.8, clearcoatRoughness: 0.14, envMapIntensity: 1.15 });
  var matZahnfleisch = new THREE.MeshPhysicalMaterial({ color: 0xc25c6b, roughness: 0.4, metalness: 0, clearcoat: 0.35, clearcoatRoughness: 0.4, envMapIntensity: 0.75 });
  var matZahnfleischTief = new THREE.MeshPhysicalMaterial({ color: 0xa04d5c, roughness: 0.62, metalness: 0 });
  var matTitan = new THREE.MeshStandardMaterial({ color: 0x7d838d, roughness: 0.36, metalness: 0.92, envMapIntensity: 1.25 });
  var matBlau = new THREE.MeshStandardMaterial({ color: 0x2b87da, roughness: 0.24, metalness: 0.6, envMapIntensity: 1.2 });
  var matSchatten = new THREE.ShadowMaterial({ opacity: 0.2 });

  var boden = new THREE.Mesh(new THREE.PlaneGeometry(30, 30), matSchatten);
  boden.rotation.x = -Math.PI / 2; boden.position.y = -1.15; boden.receiveShadow = true;
  szene.add(boden);

  var kiefer = new THREE.Group();
  kiefer.scale.setScalar(0.8);
  szene.add(kiefer);

  /* ---------- Zahnbogen ---------- */
  function bogenPunkt(t) {
    var x = (t - 0.5) * 6.6;
    var z = -0.27 * x * x + 2.3;
    return new THREE.Vector3(x, 0, z);
  }
  var bogenPunkte = [];
  for (var i = 0; i <= 48; i++) bogenPunkte.push(bogenPunkt(i / 48));
  var bogenKurve = new THREE.CatmullRomCurve3(bogenPunkte);

  // Zahnfleisch: äußerer weicher Wulst plus innerer Wulst, dazwischen sitzen die Zähne
  var wulst = new THREE.Mesh(new THREE.TubeGeometry(bogenKurve, 96, 0.72, 32, false), matZahnfleisch);
  wulst.position.y = -0.62; wulst.scale.y = 0.72;
  wulst.castShadow = true; wulst.receiveShadow = true;
  kiefer.add(wulst);
  [0, 1].forEach(function (t) {
    var p = bogenPunkt(t);
    var kappe = new THREE.Mesh(new THREE.SphereGeometry(0.72, 32, 24), matZahnfleisch);
    kappe.position.set(p.x, -0.62, p.z); kappe.scale.y = 0.72; kappe.castShadow = true;
    kiefer.add(kappe);
  });
  // Zahnfleischsaum: kleiner Wulst oben, wo die Zähne herauskommen
  var saum = new THREE.Mesh(new THREE.TubeGeometry(bogenKurve, 96, 0.42, 24, false), matZahnfleisch);
  saum.position.y = -0.22; saum.scale.y = 0.5;
  saum.receiveShadow = true;
  kiefer.add(saum);

  /* ---------- Zahnformen: weiche Blöcke, nach oben leicht ausladend, Kaufläche mit Höckern ---------- */
  function abgerundeterBlock(b, h, t, r) {
    var form = new THREE.Shape();
    var w = b / 2, d = t / 2;
    form.moveTo(-w + r, -d);
    form.lineTo(w - r, -d); form.quadraticCurveTo(w, -d, w, -d + r);
    form.lineTo(w, d - r); form.quadraticCurveTo(w, d, w - r, d);
    form.lineTo(-w + r, d); form.quadraticCurveTo(-w, d, -w, d - r);
    form.lineTo(-w, -d + r); form.quadraticCurveTo(-w, -d, -w + r, -d);
    var geo = new THREE.ExtrudeGeometry(form, { depth: h, bevelEnabled: true, bevelThickness: 0.16, bevelSize: 0.12, bevelSegments: 8, curveSegments: 12 });
    geo.rotateX(-Math.PI / 2);
    // Krone flärt nach oben leicht aus (Hals schmaler), wie ein echter Zahn
    var pos = geo.attributes.position;
    for (var i = 0; i < pos.count; i++) {
      var y = pos.getY(i); var f = 0.82 + 0.22 * Math.min(1, Math.max(0, y / h));
      pos.setX(i, pos.getX(i) * f); pos.setZ(i, pos.getZ(i) * f);
    }
    pos.needsUpdate = true; geo.computeVertexNormals();
    return geo;
  }
  function hoecker(g, liste, y, r) {
    liste.forEach(function (o) {
      var hk = new THREE.Mesh(new THREE.SphereGeometry(r, 24, 16), matZahn);
      hk.position.set(o[0], y, o[1]); hk.scale.y = 0.42; hk.castShadow = true; g.add(hk);
    });
  }
  // Zahntyp nach Position: 0 Molar, 1 Prämolar, 2 Eckzahn, 3 Schneidezahn
  function zahnTyp(k, n) {
    var m = Math.abs(k - (n - 1) / 2);
    if (m < 1.1) return 3; if (m < 2.1) return 2; if (m < 4.1) return 1; return 0;
  }
  function baueZahn(typ) {
    var g = new THREE.Group();
    var korper, h;
    if (typ === 0) {           // Molar
      h = 0.5; korper = new THREE.Mesh(abgerundeterBlock(0.56, h, 0.54, 0.17), matZahn);
      hoecker(g, [[-0.13, -0.12], [0.13, -0.12], [-0.13, 0.12], [0.13, 0.12]], h + 0.06, 0.17);
    } else if (typ === 1) {    // Prämolar
      h = 0.56; korper = new THREE.Mesh(abgerundeterBlock(0.42, h, 0.48, 0.15), matZahn);
      hoecker(g, [[0, -0.11], [0, 0.11]], h + 0.06, 0.16);
    } else if (typ === 2) {    // Eckzahn: höher, oben zulaufend
      h = 0.62; korper = new THREE.Mesh(abgerundeterBlock(0.34, h, 0.36, 0.13), matZahn);
      var spitze = new THREE.Mesh(new THREE.SphereGeometry(0.17, 24, 16), matZahn);
      spitze.position.y = h + 0.1; spitze.scale.set(1, 1.1, 0.9); spitze.castShadow = true; g.add(spitze);
    } else {                   // Schneidezahn: breit, flach
      h = 0.68; korper = new THREE.Mesh(abgerundeterBlock(0.32, h, 0.22, 0.09), matZahn);
    }
    korper.castShadow = true; korper.receiveShadow = true;
    g.add(korper);
    // Zahnfleischsaum um den Hals
    var kragen = new THREE.Mesh(new THREE.TorusGeometry(0.2, 0.06, 12, 32), matZahnfleischTief);
    kragen.rotation.x = Math.PI / 2; kragen.position.y = 0.03;
    kragen.scale.set(typ === 0 ? 1.5 : (typ === 1 ? 1.15 : (typ === 3 ? 0.95 : 0.95)), 1, typ === 3 ? 0.65 : (typ === 0 ? 1.45 : 1.25));
    g.add(kragen);
    return g;
  }

  var ANZAHL = 14, LUECKE = 3; // Lücke: 4. von links = Molar/Prämolar-Grenze
  var luecke = null;
  for (var k = 0; k < ANZAHL; k++) {
    var t = (k + 0.5) / ANZAHL;
    var p = bogenPunkt(t);
    var tangente = bogenKurve.getTangentAt(t);
    var winkel = Math.atan2(tangente.x, tangente.z);
    var typ = zahnTyp(k, ANZAHL);
    if (k === LUECKE) {
      luecke = { p: p, winkel: winkel, typ: typ };
      // Zahnfach (Alveole): dunkle Mulde im Zahnfleisch
      var mulde = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.2, 0.22, 32), matZahnfleischTief);
      mulde.position.set(p.x, -0.12, p.z); mulde.receiveShadow = true;
      kiefer.add(mulde);
      continue;
    }
    var zahn = baueZahn(typ);
    zahn.position.set(p.x, -0.22, p.z);
    zahn.rotation.y = -winkel;
    // leichte natürliche Unregelmäßigkeit
    zahn.rotation.z = (Math.sin(k * 12.9) * 0.03);
    zahn.scale.setScalar(0.97 + Math.sin(k * 7.1) * 0.03);
    kiefer.add(zahn);
  }

  /* ---------- Optional: echtes Modell (GLB) statt des gebauten Kiefers ----------
     Konfiguration in assets/kiefer-modell.js:
       window.AO_KIEFER = { glb: 'data:model/gltf-binary;base64,...' oder 'img/kiefer.glb',
                            skalierung: 1, drehung: [0,0,0], versatz: [0,0,0],
                            luecke: { x: 0, y: 0, z: 0, winkel: 0 },   // wo Schraube und Krone landen
                            kroneName: 'Krone', schraubeName: 'Implantat' }  // optionale Objektnamen im Modell
     Unter file:// funktioniert nur die Data-URI-Variante (Chrome blockiert lokale Dateizugriffe). */
  var konf = window.AO_KIEFER || null;
  var gebauterKiefer = kiefer.children.slice();
  function ladeModell() {
    if (!konf || !konf.glb || !THREE.GLTFLoader) return;
    var loader = new THREE.GLTFLoader();
    loader.load(konf.glb, function (gltf) {
      var m = gltf.scene;
      m.traverse(function (o) { if (o.isMesh) { o.castShadow = true; o.receiveShadow = true; if (o.material && o.material.isMeshStandardMaterial) o.material.envMapIntensity = 0.9; } });
      // Modell auf Bühnengröße bringen
      var box = new THREE.Box3().setFromObject(m); var groesseV = new THREE.Vector3(); box.getSize(groesseV);
      var faktor = (konf.skalierung || 1) * (6.4 / Math.max(groesseV.x, groesseV.z, 0.001));
      m.scale.setScalar(faktor);
      box.setFromObject(m); var mitte = new THREE.Vector3(); box.getCenter(mitte);
      m.position.set(-mitte.x + (konf.versatz ? konf.versatz[0] : 0), -box.min.y - 1.0 + (konf.versatz ? konf.versatz[1] : 0), -mitte.z + (konf.versatz ? konf.versatz[2] : 0));
      if (konf.drehung) m.rotation.set(konf.drehung[0], konf.drehung[1], konf.drehung[2]);
      gebauterKiefer.forEach(function (o) { kiefer.remove(o); });
      kiefer.add(m);
      // Krone/Schraube aus dem Modell übernehmen, falls benannt
      var k = konf.kroneName ? m.getObjectByName(konf.kroneName) : null;
      var sch = konf.schraubeName ? m.getObjectByName(konf.schraubeName) : null;
      if (k) { krone.visible = false; modellKrone = k; modellKroneY = k.position.y; }
      if (sch) { schraube.visible = false; modellSchraube = sch; modellSchraubeY = sch.position.y; }
      if (konf.luecke) { ZIEL.x = konf.luecke.x; ZIEL.z = konf.luecke.z; SCHRAUBE_ZIEL_Y = konf.luecke.y; KRONE_ZIEL_Y = konf.luecke.y; luecke.winkel = konf.luecke.winkel || 0; }
      gerendert = false;
    }, undefined, function (err) { if (window.console) console.warn('Kiefer-Modell konnte nicht geladen werden, gebautes Modell bleibt.', err); });
  }
  var modellKrone = null, modellSchraube = null, modellKroneY = 0, modellSchraubeY = 0;

  /* ---------- Implantat: Schraube (Titan) und Krone getrennt ---------- */
  var schraube = new THREE.Group();
  var schaft = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.08, 0.95, 32), matTitan);
  schaft.position.y = -0.475; schaft.castShadow = true;
  schraube.add(schaft);
  for (var g = 0; g < 7; g++) {
    var gewinde = new THREE.Mesh(new THREE.TorusGeometry(0.16 - g * 0.011, 0.02, 10, 40), matTitan);
    gewinde.rotation.x = Math.PI / 2;
    gewinde.position.y = -0.12 - g * 0.12;
    schraube.add(gewinde);
  }
  var plattform = new THREE.Mesh(new THREE.CylinderGeometry(0.19, 0.17, 0.08, 32), matTitan);
  plattform.position.y = 0.04; plattform.castShadow = true;
  schraube.add(plattform);
  var abutment = new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.14, 0.3, 32), matBlau);
  abutment.position.y = 0.23; abutment.castShadow = true;
  schraube.add(abutment);
  var innensechskant = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.06, 6), matTitan);
  innensechskant.position.y = 0.4;
  schraube.add(innensechskant);
  schraube.rotation.y = -luecke.winkel;
  kiefer.add(schraube);

  var krone = baueZahn(luecke.typ);
  krone.rotation.y = -luecke.winkel;
  kiefer.add(krone);

  var ZIEL = { x: luecke.p.x, z: luecke.p.z };
  var SCHRAUBE_ZIEL_Y = -0.2, SCHRAUBE_START_Y = 3.4;
  var KRONE_ZIEL_Y = -0.22, KRONE_START_Y = 3.6;

  ladeModell();

  /* ---------- Fortschritt ---------- */
  var fortschritt = reduziert ? 1 : 0;
  var buehne = wurzel.closest('.kiefer');
  function messeFortschritt() {
    if (reduziert) return 1;
    var r = buehne.getBoundingClientRect();
    var vh = window.innerHeight;
    var klebt = getComputedStyle(wurzel.closest('.kiefer__klebt')).position === 'sticky';
    if (!klebt) {
      // Ohne Klebe-Bühne (schmale Bildschirme): Verlauf, während die 3D-Fläche durchs Bild wandert
      var c = wurzel.getBoundingClientRect();
      return Math.min(1, Math.max(0, (vh * 0.9 - c.top) / (vh * 0.9 + c.height * 0.2)));
    }
    var strecke = r.height - vh;
    if (strecke <= 0) return Math.min(1, Math.max(0, (vh - r.top) / vh));
    return Math.min(1, Math.max(0, -r.top / strecke));
  }
  function easeInOut(x) { return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2; }
  function easeOut(x) { return 1 - Math.pow(1 - x, 3); }
  function abschnitt(p, a, b) { return Math.min(1, Math.max(0, (p - a) / (b - a))); }

  function anwenden(p) {
    // Phase 1: 0.00 bis 0.12  Lücke zeigen
    // Phase 2: 0.12 bis 0.55  Schraube kommt, senkt sich und dreht sich ein
    // Phase 3: 0.55 bis 0.90  Krone setzt auf
    var s = easeInOut(abschnitt(p, 0.12, 0.55));
    var sichtbarS = p > 0.05;
    schraube.visible = !modellSchraube && sichtbarS;
    schraube.position.set(ZIEL.x, SCHRAUBE_START_Y + (SCHRAUBE_ZIEL_Y - SCHRAUBE_START_Y) * s, ZIEL.z);
    schraube.rotation.y = -luecke.winkel + s * Math.PI * 6;   // eindrehen: drei volle Umdrehungen
    schraube.rotation.z = (1 - s) * 0.2;

    if (modellSchraube) { modellSchraube.visible = sichtbarS; modellSchraube.position.y = modellSchraubeY + (1 - s) * 3.4; modellSchraube.rotation.y = s * Math.PI * 6; }
    var kr = easeOut(abschnitt(p, 0.55, 0.9));
    if (modellKrone) { modellKrone.visible = p > 0.5; modellKrone.position.y = modellKroneY + (1 - kr) * 3.6; }
    krone.visible = !modellKrone && p > 0.5;
    krone.position.set(ZIEL.x, KRONE_START_Y + (KRONE_ZIEL_Y - KRONE_START_Y) * kr, ZIEL.z);
    krone.rotation.z = (1 - kr) * -0.25;
    krone.rotation.x = (1 - kr) * 0.2;

    kiefer.rotation.y = 0.5 - p * 0.62;
    kiefer.rotation.x = 0.06 + Math.sin(p * Math.PI) * 0.06;
    kiefer.position.y = 0.15 + Math.sin(p * Math.PI * 2) * 0.03;

    if (fortschrittEl) fortschrittEl.style.width = Math.round(p * 100) + '%';
    var aktiv = p < 0.12 ? 0 : (p < 0.55 ? 1 : 2);
    schritte.forEach(function (el, i) { el.classList.toggle('aktiv', i === aktiv); });
  }

  function groesse() {
    var w = canvas.clientWidth || 600, h = canvas.clientHeight || 500;
    renderer.setSize(w, h, false);
    kamera.aspect = w / h;
    kamera.updateProjectionMatrix();
  }
  groesse();
  window.addEventListener('resize', groesse);

  var sichtbar = false, laeuft = false, gerendert = false, langsam = 0, sparmodus = false;
  function schleife() {
    if (!sichtbar) { laeuft = false; return; }
    var ziel = messeFortschritt();
    var delta = ziel - fortschritt;
    if (Math.abs(delta) > 0.0008 || !gerendert) {
      fortschritt += delta * (reduziert ? 1 : 0.16);
      if (Math.abs(delta) < 0.002) fortschritt = ziel;
      anwenden(fortschritt);
      var t0 = performance.now();
      renderer.render(szene, kamera);
      gerendert = true;
      // Schwache Grafik: nach zwei langsamen Bildern Qualität senken
      if (performance.now() - t0 > 60) { langsam++; if (langsam === 2 && !sparmodus) { sparmodus = true; renderer.setPixelRatio(1); renderer.shadowMap.enabled = false; sonne.castShadow = false; groesse(); } }
    }
    window.requestAnimationFrame(schleife);
  }
  function starte() { if (!laeuft) { laeuft = true; window.requestAnimationFrame(schleife); } }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (e) { sichtbar = e[0].isIntersecting; if (sichtbar) starte(); }, { rootMargin: '200px 0px' }).observe(buehne);
  } else { sichtbar = true; starte(); }
  anwenden(fortschritt);
  renderer.render(szene, kamera);
})();
