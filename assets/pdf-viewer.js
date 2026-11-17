/*
 * Lecteur PDF intégré (documents suivis).
 * PDF.js (assets/pdfjs) n'est chargé que lorsqu'un document approche de l'écran.
 * Les pages sont dessinées une sous l'autre, à la largeur du cadre, au fil du défilement.
 */
(function () {
	'use strict';
	var cfg = window.cfPdf || {};
	var t = cfg.i18n || {};
	var ZOOMS = [0.5, 0.75, 1, 1.25, 1.5, 2, 3];
	var libPromise = null;

	function fmt(s, a, b) {
		return String(s || '').replace('%1$s', a).replace('%2$s', b);
	}

	function loadLib() {
		if (!libPromise) {
			libPromise = import(cfg.lib).then(function (m) {
				m.GlobalWorkerOptions.workerSrc = cfg.worker;
				return m;
			});
		}
		return libPromise;
	}

	function fsElement() {
		return document.fullscreenElement || document.webkitFullscreenElement || null;
	}

	function Viewer(root) {
		this.root = root;
		this.src = root.getAttribute('data-src');
		this.bar = root.querySelector('.cf-pdf__bar');
		this.box = root.querySelector('.cf-pdf__pages');
		this.pageLabel = root.querySelector('.cf-pdf__page');
		this.zoomLabel = root.querySelector('.cf-pdf__zoom');
		this.zoom = 1;
		this.pages = [];
		this.current = 1;
		this.width = 0;
		this.cssFull = false;
		root.setAttribute('data-ready', '1');
	}

	Viewer.prototype.fail = function () {
		this.root.classList.add('is-error');
		if (this.bar) { this.bar.hidden = true; }
		this.box.innerHTML = '';
		var p = document.createElement('p');
		p.className = 'cf-pdf__status';
		p.textContent = t.error || 'Erreur';
		this.box.appendChild(p);
		var fb = this.root.querySelector('.cf-pdf__fallback');
		if (fb) { fb.hidden = false; }
	};

	Viewer.prototype.start = function () {
		var self = this;
		loadLib().then(function (lib) {
			return lib.getDocument({
				url: self.src,
				withCredentials: true,
				disableRange: true,
				disableStream: true,
				isEvalSupported: false
			}).promise;
		}).then(function (pdf) {
			self.pdf = pdf;
			var all = [];
			for (var i = 1; i <= pdf.numPages; i++) { all.push(pdf.getPage(i)); }
			return Promise.all(all);
		}).then(function (pages) {
			self.build(pages);
		}).catch(function (e) {
			if (window.console) { console.warn('Lecteur PDF :', e); }
			self.fail();
		});
	};

	Viewer.prototype.build = function (pdfPages) {
		var self = this;
		var n = pdfPages.length;
		this.box.innerHTML = '';
		this.pages = pdfPages.map(function (page, i) {
			var vp = page.getViewport({ scale: 1 });
			var el = document.createElement('div');
			el.className = 'cf-pdf__sheet';
			el.setAttribute('role', 'img');
			el.setAttribute('aria-label', fmt(t.pageAlt, i + 1, n));
			self.box.appendChild(el);
			return { num: i + 1, page: page, el: el, w: vp.width, h: vp.height, canvas: null, task: null };
		});
		this.io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				var p = self.pages[+en.target.getAttribute('data-i')];
				if (!p) { return; }
				if (en.isIntersecting) { self.draw(p); } else { self.release(p); }
			});
		}, { root: this.box, rootMargin: '60% 0px' });
		this.pages.forEach(function (p, i) { p.el.setAttribute('data-i', i); });

		if (this.bar) { this.bar.hidden = false; }
		this.bind();
		this.layout(false);
	};

	/** Taille des pages (largeur du cadre × zoom), puis dessin des pages visibles. */
	Viewer.prototype.layout = function (keepPlace) {
		var self = this;
		var cs = window.getComputedStyle(this.box);
		var inner = this.box.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
		this.width = this.box.clientWidth;
		if (inner <= 0) { return; }
		var anchor = null;
		if (keepPlace && this.pages.length) {
			var cur = this.pages[this.current - 1];
			anchor = { p: cur, frac: cur.el.offsetHeight ? (this.box.scrollTop - cur.el.offsetTop) / cur.el.offsetHeight : 0 };
		}
		this.io.disconnect();
		this.pages.forEach(function (p) {
			self.release(p);
			p.scale = (inner / p.w) * self.zoom;
			p.el.style.width = Math.floor(p.w * p.scale) + 'px';
			p.el.style.height = Math.floor(p.h * p.scale) + 'px';
		});
		if (anchor) {
			this.box.scrollTop = anchor.p.el.offsetTop + anchor.frac * anchor.p.el.offsetHeight;
			this.box.scrollLeft = Math.max(0, (this.box.scrollWidth - this.box.clientWidth) / 2);
		}
		this.pages.forEach(function (p) { self.io.observe(p.el); });
		this.zoomLabel.textContent = Math.round(this.zoom * 100) + ' %';
		this.updatePage();
	};

	Viewer.prototype.draw = function (p) {
		if (p.canvas) { return; }
		var dpr = Math.min(window.devicePixelRatio || 1, 2);
		var w = p.w * p.scale;
		var h = p.h * p.scale;
		// Limite de taille (mémoire des téléphones).
		var max = 12e6;
		if (w * h * dpr * dpr > max) { dpr = Math.sqrt(max / (w * h)); }
		var canvas = document.createElement('canvas');
		canvas.width = Math.floor(w * dpr);
		canvas.height = Math.floor(h * dpr);
		canvas.setAttribute('aria-hidden', 'true');
		p.canvas = canvas;
		p.el.appendChild(canvas);
		var task = p.page.render({ canvasContext: canvas.getContext('2d'), viewport: p.page.getViewport({ scale: p.scale * dpr }) });
		p.task = task;
		task.promise.then(function () {
			if (p.task === task) { p.task = null; p.el.classList.add('is-drawn'); }
		}, function () { /* rendu annulé */ });
	};

	Viewer.prototype.release = function (p) {
		if (p.task) { p.task.cancel(); p.task = null; }
		if (p.canvas) {
			p.canvas.width = 0;
			p.canvas.height = 0;
			p.canvas.remove();
			p.canvas = null;
		}
		p.el.classList.remove('is-drawn');
	};

	Viewer.prototype.updatePage = function () {
		var mark = this.box.scrollTop + this.box.clientHeight / 3;
		var cur = 1;
		for (var i = 0; i < this.pages.length; i++) {
			if (this.pages[i].el.offsetTop <= mark) { cur = i + 1; } else { break; }
		}
		this.current = cur;
		this.pageLabel.textContent = fmt(t.page, cur, this.pages.length);
		this.btn('prev').disabled = cur <= 1;
		this.btn('next').disabled = cur >= this.pages.length;
		this.btn('out').disabled = this.zoom <= ZOOMS[0];
		this.btn('in').disabled = this.zoom >= ZOOMS[ZOOMS.length - 1];
	};

	Viewer.prototype.btn = function (act) {
		return this.root.querySelector('[data-act="' + act + '"]');
	};

	Viewer.prototype.goTo = function (num) {
		var p = this.pages[Math.max(1, Math.min(this.pages.length, num)) - 1];
		this.box.scrollTop = p.el.offsetTop - 8;
		this.updatePage();
	};

	Viewer.prototype.setZoom = function (z) {
		this.zoom = z;
		this.layout(true);
	};

	Viewer.prototype.step = function (dir) {
		var z = this.zoom;
		var next = z;
		if (dir > 0) {
			for (var i = 0; i < ZOOMS.length; i++) { if (ZOOMS[i] > z + 0.001) { next = ZOOMS[i]; break; } }
		} else {
			for (var j = ZOOMS.length - 1; j >= 0; j--) { if (ZOOMS[j] < z - 0.001) { next = ZOOMS[j]; break; } }
		}
		if (next !== z) { this.setZoom(next); }
	};

	Viewer.prototype.toggleFull = function () {
		var root = this.root;
		var native = document.fullscreenEnabled || document.webkitFullscreenEnabled;
		if (fsElement() === root) {
			(document.exitFullscreen || document.webkitExitFullscreen).call(document);
		} else if (this.cssFull) {
			this.setCssFull(false);
		} else if (native && (root.requestFullscreen || root.webkitRequestFullscreen)) {
			var r = (root.requestFullscreen || root.webkitRequestFullscreen).call(root);
			var self = this;
			if (r && r.catch) { r.catch(function () { self.setCssFull(true); }); }
		} else {
			this.setCssFull(true); // iPhone : pas de plein écran natif pour un élément.
		}
	};

	Viewer.prototype.setCssFull = function (on) {
		this.cssFull = on;
		this.root.classList.toggle('is-full', on);
		document.documentElement.classList.toggle('cf-pdf-lock', on);
		this.fullChanged();
	};

	Viewer.prototype.fullChanged = function () {
		var on = this.cssFull || fsElement() === this.root;
		this.root.classList.toggle('is-full', on);
		var b = this.btn('full');
		b.setAttribute('aria-pressed', on ? 'true' : 'false');
		var label = on ? 'Quitter le plein écran' : 'Plein écran';
		b.setAttribute('aria-label', label);
		b.title = label;
		var lbl = b.querySelector('.cf-pdf__lbl');
		if (lbl) { lbl.textContent = label; }
		var self = this;
		window.requestAnimationFrame(function () { self.layout(true); });
	};

	Viewer.prototype.bind = function () {
		var self = this;
		var ticking = false;
		this.bar.addEventListener('click', function (e) {
			var b = e.target.closest('[data-act]');
			if (!b || b.disabled) { return; }
			var act = b.getAttribute('data-act');
			if ('prev' === act) { self.goTo(self.current - 1); }
			if ('next' === act) { self.goTo(self.current + 1); }
			if ('in' === act) { self.step(1); }
			if ('out' === act) { self.step(-1); }
			if ('fit' === act) { self.setZoom(1); }
			if ('full' === act) { self.toggleFull(); }
		});
		this.box.addEventListener('scroll', function () {
			if (ticking) { return; }
			ticking = true;
			window.requestAnimationFrame(function () { ticking = false; self.updatePage(); });
		}, { passive: true });
		this.root.addEventListener('keydown', function (e) {
			if ('Escape' === e.key && self.cssFull) { self.setCssFull(false); self.btn('full').focus(); }
		});
		['fullscreenchange', 'webkitfullscreenchange'].forEach(function (ev) {
			document.addEventListener(ev, function () {
				if (fsElement() === self.root || self.root.classList.contains('is-full')) { self.fullChanged(); }
			});
		});
		var timer = null;
		var onResize = function () {
			clearTimeout(timer);
			timer = setTimeout(function () {
				if (Math.abs(self.box.clientWidth - self.width) > 1) { self.layout(true); }
			}, 150);
		};
		if (window.ResizeObserver) {
			new ResizeObserver(onResize).observe(this.box);
		} else {
			window.addEventListener('resize', onResize);
		}
	};

	function init(scope) {
		var roots = (scope || document).querySelectorAll('.cf-pdf:not([data-ready])');
		Array.prototype.forEach.call(roots, function (root) {
			var v = new Viewer(root);
			if (!cfg.lib || !window.IntersectionObserver || !window.Promise) { v.fail(); return; }
			// Chargement quand le document approche de l'écran.
			var io = new IntersectionObserver(function (entries) {
				if (entries.some(function (en) { return en.isIntersecting; })) {
					io.disconnect();
					v.start();
				}
			}, { rootMargin: '400px 0px' });
			io.observe(root);
		});
	}
	window.cfPdfInit = init;

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', function () { init(); });
	} else {
		init();
	}
	// Elementor (aperçu dans l'éditeur : widget rendu à nouveau).
	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', function () {
			if (window.elementorFrontend && window.elementorFrontend.hooks) {
				window.elementorFrontend.hooks.addAction('frontend/element_ready/cf_document.default', function ($el) { init($el[0]); });
			}
		});
	}
}());
