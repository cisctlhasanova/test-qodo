$(function() {
  const bi_key            = global_vars?.global_bi_key ?? `lbi_${body_lstoken}`;
  const savingDisabled    = global_vars?.bonusSavingDisabled ?? false;
  let bonus_info_array    = !savingDisabled ? (Store.get(bi_key) || []) : [];
  const bs_selector       = global_vars?.bonus_section_selector ?? '[data-role="bonus-section"]';
  const $section          = $(bs_selector);
  global_vars.show_bonus  = false;


  // console.log({bonus_info_array, bi_key});
  if (typeof bonus_info_array.bonus_amount !== "undefined") {
    let bonus_rate    = +$section.find(`[data-role="bonus-rate"]`).val();
    $section.find(`[data-role="total-amount"]`).val(global_number_format(bonus_info_array.total_amount ?? 0, "price", true));
    $section.find(`[data-role="bonus-rate"]`).val(bonus_info_array.bonus_rate || bonus_rate);
    $section.find(`[data-role="bonus-amount"]`).val(global_number_format(bonus_info_array.bonus_amount ?? 0, "price", true));
  } else {
    let total_amount  = +$section.find(`[data-role="total-amount"]`).val(),
    let bonus_rate    = +$section.find(`[data-role="bonus-rate"]`).val()
    if (total_amount && bonus_rate) {
      $section.find(`[data-role="bonus-amount"]`).val(global_number_format((bonus_rate * total_amount) / 100, "price", true));
    }
  }

  global_fns.resetBonus = () => {
    // console.log({msg: "called", savingDisabled});
    if (!savingDisabled) {
      $section.find(`[data-role="total-amount"], [data-role="bonus-amount"]`).val("");
      Store.unset(bi_key);
    } else {
      bonus_info_array = [];
    }
  }

  global_fns.setBonusTotal = (amount = null) => {
    const bonus_rate = $section.find(`[data-role="bonus-rate"]`).val();
    $section.find(`[data-role="total-amount"]`).val(global_number_format(amount ?? (bonus_info_array.total_amount ?? 0), "price", true));
    calculateBonus('total-amount');
  }

  global_fns.showBonus = (amount) => {
    if(global_vars.show_bonus) {
      global_fns.setBonusTotal(amount);
      return;
    }
    global_vars.show_bonus = true;
    global_fns.setBonusTotal(amount);
    $section.removeClass(CLASSES.HIDDEN);
    bonus_event_content = globalBuyer.vars("Customer.bonus_events").map(v => `<option value="${v.id}">${v.name}</option>`).join("");
    $(`[data-role="bonus-events"]`).append(bonus_event_content).change();
  }

  global_fns.hideBonus = (amount) => {
    global_vars.show_bonus = false;
    $section.addClass(CLASSES.HIDDEN);
  }

  global_fns.getBonusAmount = (amount) => {
    return $section.find(`[data-role="bonus-amount"]`).val();
  }

  global_fns.getTotalAmount = (amount) => {
    return $section.find(`[data-role="total-amount"]`).val();
  }

  global_fns.getBonusRate = (amount) => {
    return $section.find(`[data-role="bonus-rate"]`).val();
  }

  global_fns.getBonusEvent = () => {
    return $section.find(`[data-role="bonus-events"]`).val();
  }

  const calculateBonus = (input) => {
    let total_amount    = $section.find(`[data-role="total-amount"]`).val();
    let bonus_rate      = $section.find(`[data-role="bonus-rate"]`).val();
    let bonus_amount    = $section.find(`[data-role="bonus-amount"]`).val();
    if (!total_amount) {
      $section.find(`[data-role="bonus-rate"], [data-role="bonus-amount"]`).val(0);
      return;
    } else if(total_amount <= 0) {
      $section.find(`[data-role="total-amount"]`).val(global_number_format(0, "price", true));
      $section.find(`[data-role="bonus-amount"]`).val(global_number_format(0, "price", true));
      return;
    }

    if (+bonus_amount > +total_amount) {
      bonus_amount = total_amount;
      $section.find(`[data-role="bonus-amount"]`).val(global_number_format(bonus_amount, "price", true));
    }

    bonus_rate          = Math.min(100, Math.max(0, bonus_rate));

    if (input === "bonus-rate") {
      bonus_amount = (bonus_rate / 100) * total_amount
    } else if(input === "bonus-amount") {
      bonus_rate = bonus_amount/total_amount * 100;
    } else {
      bonus_amount = (bonus_rate / 100) * total_amount;
    }
    $section.find(`[data-role="bonus-amount"]`).val(global_number_format(bonus_amount, "price", true));
    $section.find(`[data-role="bonus-rate"]`).val(bonus_rate.toFixed(2));

    bonus_info_array = {
      bonus_rate,
      total_amount,
      bonus_amount
    };

    if (!savingDisabled) {
      Store.set(bi_key, bonus_info_array);
      // localStorage.setItem(bi_key, JSON.stringify(bonus_info_array));
    }
  }


  $(document).on("change", ` ${bs_selector} [data-role="bonus-rate"], ${bs_selector} [data-role="total-amount"], ${bs_selector} [data-role="bonus-amount"]`, function () {
		$section.find(`[data-role="bonus-text"]`).text("");
		$section.find(`[data-role="bonus-tr"]`).addClass(CLASSES.HIDDEN);
    let type = $(this).data("role");

    if (["total-amount", "bonus-amount"].includes(type)) {
      $(this).val(global_number_format($(this).val(), "price", true))
    }
		if ($(this).val().trim() === "") {
      $(this).val(0)
		}
		calculateBonus(type);
	})


})
