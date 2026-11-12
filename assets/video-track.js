/**
 * Validation automatique des vidéos suivies.
 * Compte les secondes réellement regardées (fichier vidéo, YouTube, Vimeo) ;
 * au-delà du seuil choisi (ex. 90 %), la vidéo est enregistrée comme vue.
 * Option « empêcher d'avancer » : impossible d'aller plus loin que ce qui a été regardé.
 */
(function () {
	'use strict';
	var boxes = document.querySelectorAll('.cf-video-track');
	if (!boxes.length || !window.cfVideo) {
		return;
	}

	function loadScript(src, ready) {
		var s = document.querySelector('script[src="' + src + '"]');
		if (!s) {
			s = document.createElement('script');
			s.src = src;
			document.head.appendChild(s);
		}
		var tries = 0;
		var t = setInterval(function () {
			if (ready()) { clearInterval(t); s.dispatchEvent(new Event('cf-ready')); }
			if (++tries > 100) { clearInterval(t); }
		}, 100);
		return s;
	}


	/** Parcours dans l'ordre : le contenu suivant vient d'être débloqué. */
	function offerNext(after, next) {
		if (!next || !after) { return; }
		var a = document.createElement('a');
		a.className = 'if-btn if-btn--small cf-next';
		a.href = '#' + next;
		a.textContent = (window.cfNextLabel || 'Continuer') + ' →';
		a.addEventListener('click', function (e) { e.preventDefault(); location.hash = next; location.reload(); });
		after.appendChild(document.createTextNode(' '));
		after.appendChild(a);
	}

	function Tracker(box) {
		this.box = box;
		this.threshold = parseInt(box.getAttribute('data-threshold'), 10) || 90;
		this.noSkip = box.getAttribute('data-noskip') === '1';
		this.done = box.getAttribute('data-done') === '1';
		this.seen = {};
		this.count = 0;
		this.maxReached = 0;
		this.last = null;
		this.status = box.querySelector('.cf-video-status');
	}

	/** Appelé régulièrement avec la position actuelle et la durée (en secondes). */
	Tracker.prototype.tick = function (time, duration, playing) {
		if (!duration || !isFinite(duration)) { return null; }
		// Avance rapide interdite : retour au point le plus loin déjà regardé.
		if (this.noSkip && !this.done && time > this.maxReached + 2) {
			return this.maxReached;
		}
		if (playing && this.last !== null && time >= this.last && time - this.last < 3) {
			for (var s = Math.floor(this.last); s <= Math.floor(time); s++) {
				if (!this.seen[s]) { this.seen[s] = true; this.count++; }
			}
		}
		this.last = time;
		if (time > this.maxReached) { this.maxReached = time; }
		var pct = Math.min(100, Math.round(this.count / Math.ceil(duration) * 100));
		if (!this.done && this.status) {
			this.status.textContent = cfVideo.watched.replace('%d', pct) + ' / ' + this.threshold + ' %';
		}
		if (!this.done && pct >= this.threshold) { this.finish(); }
		return null;
	};

	Tracker.prototype.finish = function () {
		var self = this;
		this.done = true;
		var b = this.box;
		var body = new URLSearchParams({ action: 'if_video_done', notion_id: b.getAttribute('data-notion'), key: b.getAttribute('data-key'), nonce: b.getAttribute('data-nonce') });
		fetch(cfVideo.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				if (!r || !r.success) { self.done = false; return; }
				if (self.status) { self.status.textContent = cfVideo.done; self.status.classList.add('is-done'); }
				offerNext(self.status, r.data.next);
				var bar = document.querySelector('.if-summary .if-progress span');
				var txt = document.querySelector('.if-summary small');
				if (bar && r.data.progress) { bar.style.width = r.data.progress.percent + '%'; }
				if (txt && r.data.progress) { txt.textContent = r.data.progress.percent + ' % — ' + r.data.progress.done + ' / ' + r.data.progress.total; }
				// Bouton « J'ai terminé » à côté : on l'affiche comme terminé.
				var next = b.nextElementSibling;
				if (next && next.classList.contains('if-complete')) {
					next.innerHTML = '<span class="if-done">' + cfVideo.done + '</span>';
				}
			})
			.catch(function () { self.done = false; });
	};

	/* ---------- Fichier vidéo (balise <video>) ---------- */
	function trackHtml5(tr, video) {
		var update = function () {
			var back = tr.tick(video.currentTime, video.duration, !video.paused);
			if (back !== null) { video.currentTime = back; }
		};
		video.addEventListener('timeupdate', update);
		video.addEventListener('seeking', function () {
			if (tr.noSkip && !tr.done && video.currentTime > tr.maxReached + 2) { video.currentTime = tr.maxReached; }
			tr.last = null;
		});
		video.addEventListener('play', function () { tr.last = video.currentTime; });
	}

	/* ---------- YouTube ---------- */
	var ytQueue = [];
	function trackYouTube(tr, iframe) {
		var src = iframe.getAttribute('src') || '';
		if (src.indexOf('enablejsapi=1') === -1) {
			src += (src.indexOf('?') === -1 ? '?' : '&') + 'enablejsapi=1&origin=' + encodeURIComponent(location.origin);
			iframe.setAttribute('src', src);
		}
		ytQueue.push([tr, iframe]);
		var start = function () {
			while (ytQueue.length) {
				(function (pair) {
					var timer = null;
					var player = new YT.Player(pair[1], {
						events: {
							onStateChange: function (e) {
								clearInterval(timer);
								if (e.data === YT.PlayerState.PLAYING) {
									pair[0].last = player.getCurrentTime();
									timer = setInterval(function () {
										var back = pair[0].tick(player.getCurrentTime(), player.getDuration(), true);
										if (back !== null) { player.seekTo(back, true); pair[0].last = back; }
									}, 250);
								} else {
									pair[0].last = null;
								}
							}
						}
					});
				})(ytQueue.shift());
			}
		};
		if (window.YT && window.YT.Player) { start(); return; }
		var prev = window.onYouTubeIframeAPIReady;
		window.onYouTubeIframeAPIReady = function () { if (prev) { prev(); } start(); };
		loadScript('https://www.youtube.com/iframe_api', function () { return window.YT && window.YT.Player; });
	}

	/* ---------- Vimeo ---------- */
	function trackVimeo(tr, iframe) {
		var go = function () {
			var player = new Vimeo.Player(iframe);
			player.on('play', function (d) { tr.last = d.seconds; });
			player.on('pause', function () { tr.last = null; });
			player.on('seeked', function (d) {
				if (tr.noSkip && !tr.done && d.seconds > tr.maxReached + 2) { player.setCurrentTime(tr.maxReached); }
				tr.last = null;
			});
			player.on('timeupdate', function (d) {
				var back = tr.tick(d.seconds, d.duration, true);
				if (back !== null) { player.setCurrentTime(back); tr.last = back; }
			});
		};
		if (window.Vimeo && window.Vimeo.Player) { go(); return; }
		loadScript('https://player.vimeo.com/api/player.js', function () { return window.Vimeo && window.Vimeo.Player; }).addEventListener('cf-ready', go);
	}

	boxes.forEach(function (box) {
		var tr = new Tracker(box);
		var video = box.querySelector('video');
		var iframe = box.querySelector('iframe');
		if (video) {
			trackHtml5(tr, video);
		} else if (iframe && /youtube(-nocookie)?\.com\/embed/.test(iframe.src)) {
			trackYouTube(tr, iframe);
		} else if (iframe && /player\.vimeo\.com/.test(iframe.src)) {
			trackVimeo(tr, iframe);
		} else if (tr.status && !tr.done) {
			tr.status.textContent = '';
		}
	});
})();
