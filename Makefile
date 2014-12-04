# Make file for installing NewsletterMan
# [newsletterman]

# Define Vars

INSTALL_DIR=$(NLM_ROOT)/usr/share/newsletterman
CONFIG_DIR=$(INSTALL_DIR)/config
CRON_TAB=$(INSTALL_DIR)/config/newsletterman_crontab

SYS_CRON_TAB=$(NLM_ROOT)/etc/cron.d/newsletterman_crontab

GIT_REPO=https://github.com/cyriltata/newsletterman.git
GIT=$(shell echo "git --git-dir=$(INSTALL_DIR)/.git --work-tree=$(INSTALL_DIR)")

COMPOSER=composer

all: install

install: install_files install_dependencies clean
	@echo "newsletterman has been installed successfully to $(INSTALL_DIR)."
	@echo "\nConfigure with right parameters at $(CONFIG_DIR)/config.php and then activate cron at $(SYS_CRON_TAB)"

install_files:
	@echo "Installing files .....";

	@install -d -m 0744 $(INSTALL_DIR)

	$(GIT) init
	$(GIT) remote add origin $(GIT_REPO)
	$(GIT) pull origin master

	@chmod 0755 $(INSTALL_DIR)/bin/newsletterman.php
	@[ ! -f $(CONFIG_DIR)/config.php ] && cp $(CONFIG_DIR)/config.dist.php $(CONFIG_DIR)/config.php
	@chmod 0644 $(CONFIG_DIR)/config.php

	@[ ! -f $(SYS_CRON_TAB) ] && cp $(CRON_TAB) $(SYS_CRON_TAB)

	@echo "Done."

install_dependencies:
	@echo "Installing dependencies ....."

	cd $(INSTALL_DIR) && $(COMPOSER) install
	cd $(INSTALL_DIR) && $(COMPOSER) update
	@echo "Done"

uninstall:
	@echo "Uninstalling config files, log directory and app files .....";
	@rm -rf $(INSTALL_DIR)
	@rm -rf $(SYS_CRON_TAB)
	@echo "Done."

update: update_files clean
	@echo "Updating...."
	@echo "$$DONE"

update_files:
	$(GIT) reset --hard
	$(GIT) pull origin master

	cd $(INSTALL_DIR) && $(COMPOSER) update

clean:
	@echo "Installation completed..."