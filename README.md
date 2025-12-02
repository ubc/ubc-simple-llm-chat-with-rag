# UBC Simple LLM Chat with RAG

A simple chat interface for WordPress that integrates with the [UBC RAG Plugin](https://github.com/ubc/ubc-rag) to provide Retrieval-Augmented Generation (RAG) capabilities using OpenAI or Ollama.

## Requirements

*   **WordPress**: 6.0+
*   **PHP**: 7.4+
*   **UBC RAG Plugin**: Must be installed and activated. This plugin relies on `\UBC\RAG\API` to fetch context.
*   **Qdrant Vector Database**: Currently, the UBC RAG plugin requires a Qdrant instance (MySQL implementation is pending). You can use the [UBC Dockerized Qdrant Setup](https://github.com/ubc/tlef-qdrant) for easy deployment.

## Installation

1.  Ensure the **UBC RAG Plugin** is installed and configured.
2.  Download and install this plugin.
3.  Activate the plugin through the 'Plugins' menu in WordPress.
4.  If the UBC RAG plugin is not active, you will see an error notice and the chat functionality will not work.

## Configuration

Go to **Settings > UBC Chat RAG** to configure the plugin.

### LLM Provider
You can choose between **OpenAI** and **Ollama** as your LLM backend.

#### OpenAI
*   **Model Name**: e.g., `gpt-4o`, `gpt-3.5-turbo`.
*   **API Key**: Your OpenAI API Key.
*   **Temperature**: Controls randomness (0.0 to 1.0). Default is `0.7`.

#### Ollama (Local LLM)
*   **Server URL**: The URL of your Ollama instance (e.g., `http://localhost:11434`).
*   **Model Name**: The model you want to use (e.g., `llama3`, `mistral`).
*   **API Key**: Optional. Only required if your Ollama instance is behind an auth proxy.
*   **Temperature**: Default is `0.7`.

### General Settings
*   **System Prompt**: The instructions given to the LLM.
    *   *Example*: "You are a helpful assistant for the University of British Columbia. Answer questions based on the provided context. If the answer is not in the context, say you don't know."
*   **Minimum Similarity Score**: The threshold for RAG results (0.0 to 1.0). Only content with a similarity score above this value will be included in the context. Default is `0.5`.

## Usage

Add the shortcode `[ubc_simple_chat]` to any page or post to display the chat interface.

```shortcode
[ubc_simple_chat]
```

## Features

*   **RAG Integration**: Automatically fetches relevant content from your vector database based on the user's query.
*   **Source Attribution**: Displays the sources used to generate the answer, including links to the original content.
*   **Chat History**: Persists chat sessions for logged-in users.
*   **Provider Flexibility**: Switch between cloud-based (OpenAI) and local (Ollama) models easily.
