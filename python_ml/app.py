# python_ml/app.py — Flask API for Decision Tree predictions
# Run this separately: python app.py
# PHP will call http://localhost:5000/predict via HTTP

from flask import Flask, request, jsonify
from decision_tree import predict
from flask_cors import CORS

app = Flask(__name__)
CORS(app)  # Allow PHP (different port) to call this API

@app.route('/predict', methods=['POST'])
def predict_endpoint():
  data = request.get_json()
  if not data:
    return jsonify({'error': 'No data provided'}), 400
  result = predict(data)
  return jsonify(result)

@app.route('/health', methods=['GET'])
def health():
  return jsonify({'status': 'ok', 'service': 'UdM-RADAR ML API'})

if __name__ == '__main__':
  app.run(host='127.0.0.1', port=5000, debug=True)