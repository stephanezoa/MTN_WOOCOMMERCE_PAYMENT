(function ($, window, document) {
	"use strict";

	function getCurrentStep() {
		var step = parseInt($("[data-momopay-step]").attr("data-momopay-step"));

		return !step ? 1 : step;
	}

	function setSaveBtnText() {
		var $btns = $("button.woocommerce-save-button, input.woocommerce-save-button, button[name=save], input[name=save]");
		$btns.each(function () {
			$(this).show();
		});

		if (location.search.indexOf("section=momopay") == -1) return;

		var step = getCurrentStep();
		var text = "";

		switch (step) {
			case 1:
				text = "Continuer";
				break;
			case 2:
				text = "Valider et enregistrer les clés";
				break;
			case 3:
				text = "Enregistrer les modifications";
				break;
		}

		if (text) {
			$btns.each(function () {
				if (this.tagName === "INPUT") {
					$(this).val(text);
				} else {
					$(this).text(text);
				}
				$(this).show();
			});
		}
	}

	function updValidateBox(mode) {
		if (mode == 1) {
			$("[data-momoadv-box=live]").addClass("hide");
			$("[data-momoadv-box=sandbox]").removeClass("hide");
			return;
		}

		$("[data-momoadv-box=live]").removeClass("hide");
		$("[data-momoadv-box=sandbox]").addClass("hide");
	}

	function initApiKeyToggle() {
		var $apiKeyInput = $("#woocommerce_momopay_api_key");
		if ($apiKeyInput.length && !$("#momopay_toggle_api_key").length) {
			var $btn = $('<button type="button" id="momopay_toggle_api_key" class="button button-secondary momopay-toggle-pw-btn" style="margin-left: 8px;">Afficher</button>');
			$apiKeyInput.after($btn);
			$btn.on("click", function (e) {
				e.preventDefault();
				if ($apiKeyInput.attr("type") === "password") {
					$apiKeyInput.attr("type", "text");
					$(this).text("Masquer");
				} else {
					$apiKeyInput.attr("type", "password");
					$(this).text("Afficher");
				}
			});
		}
	}

	$(document).ready(function () {
		$("body").on("change", "#woocommerce_momopay_mode", function () {
			updValidateBox(this.value);
		});
		setSaveBtnText();
		initApiKeyToggle();
	});
})(jQuery, window, document);
