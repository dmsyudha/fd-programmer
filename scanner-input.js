if (parseFloat(claimField.value) < 0) {
    referenceField.disabled = false;
    claimField.disabled = false;
    redeemButton.classList.remove('is-loading');
    parentModal.querySelector('#redemption_error').innerHTML = `Cannot use negative amount.`;
    return;
  }