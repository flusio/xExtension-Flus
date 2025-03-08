.DEFAULT_GOAL := help

.PHONY: release
release: ## Release a new version (take a VERSION argument)
ifndef VERSION
	$(error You need to provide a "VERSION" argument)
endif
	sed -i 's/"version": ".*"/"version": "$(VERSION)"/' metadata.json
	$(EDITOR) CHANGELOG.md
	git add .
	git commit -m "release: Publish version v$(VERSION)"
	git tag -a v$(VERSION) -m "Release version v$(VERSION)"

.PHONY: help
help:
	@grep -h -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
